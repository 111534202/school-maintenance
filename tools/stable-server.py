"""校園設備維保系統：Windows 本機穩定網頁伺服器（展示、驗收、開發都建議用這個）。

為什麼不用 `php artisan serve`？
  PHP 內建伺服器（php -S）在 Windows 上會「隨機」把較大的回應截斷在固定大小（例如 65280、129280 位元組），
  然後卡約 19 秒才斷線：樣式檔（css）、圖表程式（js）或頁面被截一半，畫面就會沒樣式、跑版、按鈕沒反應、對話框空白。
  這是 PHP 內建伺服器在 Windows 上的問題，不是網站程式的問題。

這支伺服器怎麼避開：
  1. public/ 底下的靜態檔（css、js、圖片）直接從磁碟讀出來，整份送出，不經過 PHP 內建伺服器；
  2. 動態頁面每次請求啟動一個 php-cgi 處理，從程式的標準輸出把「完整的」結果讀回來，
     先算好 Content-Length 再回應，瀏覽器收到的一定是完整的。
  只用 Python 內建套件，不需要安裝任何東西；PHP 用的是和 `php` 同一個資料夾的 php-cgi.exe。

用法（在專案根目錄）：
  python tools/stable-server.py                              # 開在 http://127.0.0.1:8000
  python tools/stable-server.py --port 8080 --host 0.0.0.0   # 讓同網路的其他電腦連進來（展示用）
  環境變數 PHP_CGI 可指定 php-cgi.exe 的完整路徑（預設自動找 php 旁邊的）。
正式上線請用 Apache 或 nginx + PHP-FPM，不要用這支。
"""
import argparse
import gzip
import mimetypes
import os
import shutil
import subprocess
import sys
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import unquote, urlsplit

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PUBLIC = os.path.join(ROOT, 'public')
INDEX = os.path.join(PUBLIC, 'index.php')
INI_DIR = os.path.join(ROOT, 'tools', 'php-ini')   # 額外的 PHP 設定（上傳上限等），見 tools/php-ini/stable-server.ini

mimetypes.add_type('text/css', '.css')
mimetypes.add_type('application/javascript', '.js')
mimetypes.add_type('font/woff2', '.woff2')
mimetypes.add_type('image/svg+xml', '.svg')

GZIP_TYPES = ('text/', 'application/javascript', 'application/json', 'image/svg+xml')
HOP = {'connection', 'keep-alive', 'transfer-encoding', 'upgrade', 'content-length'}

php_cgi = None                       # php-cgi.exe 的路徑（main 裡找）
php_slots = threading.Semaphore(8)   # 同時最多 8 個 php-cgi，避免瀏覽器一次開很多連線把電腦拖垮
gzip_cache = {}                      # 靜態檔壓縮結果快取：{(路徑, 修改時間, 大小): 壓縮後內容}
gzip_lock = threading.Lock()


def find_php_cgi():
    env = os.environ.get('PHP_CGI')
    if env and os.path.isfile(env):
        return env
    php = shutil.which('php')
    if php:
        candidate = os.path.join(os.path.dirname(php), 'php-cgi.exe' if os.name == 'nt' else 'php-cgi')
        if os.path.isfile(candidate):
            return candidate
    found = shutil.which('php-cgi')
    if found:
        return found
    sys.exit('找不到 php-cgi。請把 PHP 資料夾加入 PATH，或設定環境變數 PHP_CGI 指向 php-cgi.exe。')


class Handler(BaseHTTPRequestHandler):
    # HTTP/1.1：每個回應都有 Content-Length，瀏覽器可以重複使用連線，頁面載入較快。
    protocol_version = 'HTTP/1.1'
    server_version = 'StableServer'
    sys_version = ''

    # ---------- 回應 ----------
    def reply(self, status, headers, body, head_only=False):
        self.send_response(status)
        for key, value in headers:
            self.send_header(key, value)
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        if not head_only:
            self.wfile.write(body)

    def text_error(self, status, message):
        self.reply(status, [('Content-Type', 'text/plain; charset=utf-8')], message.encode('utf-8'), self.command == 'HEAD')

    # ---------- 靜態檔 ----------
    def static_file(self):
        """public/ 底下真的存在的檔案直接送出；成功處理回傳 True。"""
        if self.command not in ('GET', 'HEAD'):
            return False
        path = unquote(urlsplit(self.path).path)
        if path in ('', '/') or path.endswith('.php') or '\x00' in path:
            return False
        full = os.path.normpath(os.path.join(PUBLIC, path.lstrip('/\\')))
        if os.path.commonpath([full, PUBLIC]) != PUBLIC or not os.path.isfile(full):
            return False
        if any(part.startswith('.') for part in os.path.relpath(full, PUBLIC).split(os.sep)):
            return False   # .env、.htaccess 這類隱藏檔不外送

        stat = os.stat(full)
        etag = '"%x-%x"' % (int(stat.st_mtime), stat.st_size)
        ctype = mimetypes.guess_type(full)[0] or 'application/octet-stream'
        headers = [('Content-Type', ctype), ('ETag', etag), ('Cache-Control', 'no-cache')]
        if self.headers.get('If-None-Match') == etag:
            self.send_response(304)
            self.send_header('ETag', etag)
            self.send_header('Content-Length', '0')
            self.end_headers()
            return True

        with open(full, 'rb') as handle:
            data = handle.read()
        if len(data) != stat.st_size:
            self.text_error(500, '讀取檔案時大小不一致，請重新整理')
            return True
        if len(data) >= 2048 and ctype.startswith(GZIP_TYPES) and 'gzip' in self.headers.get('Accept-Encoding', ''):
            key = (full, stat.st_mtime_ns, stat.st_size)
            with gzip_lock:
                packed = gzip_cache.get(key)
            if packed is None:
                packed = gzip.compress(data, 6)
                with gzip_lock:
                    gzip_cache[key] = packed
            data = packed
            headers += [('Content-Encoding', 'gzip'), ('Vary', 'Accept-Encoding')]
        self.reply(200, headers, data, self.command == 'HEAD')
        return True

    # ---------- 動態頁面：php-cgi ----------
    def run_php(self):
        parts = urlsplit(self.path)
        length = int(self.headers.get('Content-Length') or 0)
        if self.headers.get('Transfer-Encoding', '').lower() == 'chunked':
            self.text_error(411, '不支援 chunked 上傳')
            return
        body = self.rfile.read(length) if length else b''

        host, port = self.server.server_address[:2]
        env = dict(os.environ)
        env.update({
            'GATEWAY_INTERFACE': 'CGI/1.1',
            'SERVER_SOFTWARE': 'StableServer',
            'SERVER_PROTOCOL': 'HTTP/1.1',
            'SERVER_NAME': (self.headers.get('Host') or f'{host}:{port}').split(':')[0],
            'SERVER_PORT': str(port),
            'REQUEST_METHOD': self.command,
            'REQUEST_URI': self.path,
            'QUERY_STRING': parts.query,
            'SCRIPT_NAME': '/index.php',
            'SCRIPT_FILENAME': INDEX,
            'PATH_INFO': '',
            'DOCUMENT_ROOT': PUBLIC,
            'REMOTE_ADDR': self.client_address[0],
            'REDIRECT_STATUS': '200',
            'CONTENT_LENGTH': str(len(body)),
            'CONTENT_TYPE': self.headers.get('Content-Type', ''),
        })
        for key, value in self.headers.items():
            name = key.upper().replace('-', '_')
            if name not in ('CONTENT_TYPE', 'CONTENT_LENGTH'):
                env['HTTP_' + name] = value

        with php_slots:
            try:
                # 上傳上限：Windows 的 PHP 預設單檔只有 2 MB，但系統允許一筆報修附件加起來 5 MB，所以要調高。
                # 不能用命令列 -d：php-cgi 有 QUERY_STRING 時會把它當命令列，-d 會被忽略；改用額外的設定檔資料夾（接在原有的後面）。
                env['PHP_INI_SCAN_DIR'] = os.pathsep.join(filter(None, [os.environ.get('PHP_INI_SCAN_DIR'), INI_DIR]))
                proc = subprocess.run([php_cgi], input=body, env=env, cwd=PUBLIC,
                                      capture_output=True, timeout=120)
            except subprocess.TimeoutExpired:
                self.text_error(504, 'PHP 執行逾時（120 秒）')
                return

        raw = proc.stdout
        marker = raw.find(b'\r\n\r\n')
        sep = 4
        if marker == -1:
            marker = raw.find(b'\n\n')
            sep = 2
        if marker == -1:
            detail = (proc.stderr or raw)[-800:].decode('utf-8', 'replace')
            self.text_error(500, 'PHP 沒有回傳正常的回應：\n' + detail)
            return

        status = 200
        headers = []
        for line in raw[:marker].decode('latin-1').splitlines():
            if ':' not in line:
                continue
            key, _, value = line.partition(':')
            key, value = key.strip(), value.strip()
            if key.lower() == 'status':
                status = int(value.split()[0])
            elif key.lower() not in HOP:
                headers.append((key, value))
        self.reply(status, headers, raw[marker + sep:], self.command == 'HEAD')

    def handle_request(self):
        try:
            if not self.static_file():
                self.run_php()
        except (BrokenPipeError, ConnectionResetError):
            pass   # 瀏覽器已經離開（例如按了重新整理）
        except Exception as error:  # noqa: BLE001
            try:
                self.text_error(500, f'伺服器錯誤：{error}')
            except Exception:  # noqa: BLE001
                pass

    do_GET = do_POST = do_PUT = do_PATCH = do_DELETE = do_HEAD = do_OPTIONS = handle_request

    def log_message(self, fmt, *args):
        if os.environ.get('STABLE_SERVER_LOG'):
            sys.stderr.write('%s %s\n' % (self.command, self.path))


class Server(ThreadingHTTPServer):
    request_queue_size = 256   # 預設只有 5：瀏覽器同時開很多連線時，後面的會被丟掉、要等好幾秒才重傳
    daemon_threads = True
    close_wait = 10            # 秒：回應送完後，最多等對方多久先關閉連線

    def shutdown_request(self, request):
        """關閉連線前先等對方關閉（最多 close_wait 秒）。

        Python 預設在回應交給系統後立刻 shutdown(SHUT_WR) 再 close；這時資料常常還沒送完，
        在 Windows 本機實測會讓最後一段卡住，約 19 秒後變成 ConnectionResetError（瀏覽器以外、
        用 Connection: close 的客戶端會收到不完整的檔案或頁面）。先送 FIN 再讀到對方關閉一樣會發生，
        所以要等對方收完（依 Content-Length 判斷）自己關閉，這裡才送 FIN。
        """
        deadline = time.monotonic() + self.close_wait
        try:
            while (left := deadline - time.monotonic()) > 0:
                request.settimeout(left)
                if not request.recv(4096):
                    break   # 對方已關閉
        except OSError:
            pass            # 逾時或對方直接中斷：照常關閉
        super().shutdown_request(request)


def main():
    global php_cgi
    parser = argparse.ArgumentParser(description='校園設備維保系統：Windows 穩定網頁伺服器')
    parser.add_argument('--host', default='127.0.0.1')
    parser.add_argument('--port', type=int, default=8000)
    args = parser.parse_args()
    php_cgi = find_php_cgi()
    shown = '127.0.0.1' if args.host == '0.0.0.0' else args.host
    print(f'網站已啟動：http://{shown}:{args.port}   （PHP：{php_cgi}）')
    print('按 Ctrl+C 結束。')
    try:
        Server((args.host, args.port), Handler).serve_forever()
    except KeyboardInterrupt:
        print('\n已結束。')


if __name__ == '__main__':
    main()
