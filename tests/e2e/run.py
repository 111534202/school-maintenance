#!/usr/bin/env python3
"""
瀏覽器端到端測試（E2E）的執行器：用真的 Chrome 照使用者的操作方式，把主要流程從頭到尾走一遍。

做的事（全部自動）：
  1. 建立專用的測試資料庫「school_maintenance_e2e」（不會碰你平常開發用的資料庫）。
  2. 在裡面執行所有 migration 與示範資料（php artisan migrate:fresh --seed）。
  3. 啟動一個只給測試用的網站（預設 http://127.0.0.1:8765）。
  4. 依序執行 tests/e2e/scenarios.py 裡的每個情境，印出通過／失敗。
  5. 關掉網站、刪除測試資料庫。有任何失敗，結束代碼是 1。

用法（專案根目錄）：
  python tests/e2e/run.py                 # 全部跑
  python tests/e2e/run.py --only repair   # 只跑名稱含 repair 的情境
  python tests/e2e/run.py --headed        # 顯示瀏覽器視窗，看著它操作
  python tests/e2e/run.py --keep-db       # 跑完不刪測試資料庫（方便自己去看資料）

第一次使用要先安裝（只需一次）：
  pip install playwright
  playwright install chromium     # 若電腦有 Google Chrome 可省略，會優先用 Chrome
需要 PHP 8.4、MySQL 已啟動、專案的 .env 已設定好資料庫帳密（和平常開發一樣）。
"""
import argparse
import os
import platform
import subprocess
import sys
import time
import traceback
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(Path(__file__).resolve().parent))

E2E_DB = os.environ.get('E2E_DB', 'school_maintenance_e2e')
PORT = int(os.environ.get('E2E_PORT', '8765'))
BASE = f'http://127.0.0.1:{PORT}'


def run(cmd, env=None, check=True, quiet=False):
    result = subprocess.run(cmd, cwd=ROOT, env=env, capture_output=True, text=True, encoding='utf-8', errors='replace')
    if check and result.returncode != 0:
        print(result.stdout[-2000:], result.stderr[-2000:])
        raise SystemExit(f'指令失敗：{" ".join(cmd)}')
    if not quiet and result.stdout.strip():
        print(result.stdout.strip()[-300:])
    return result


def wait_for_server(timeout=30):
    deadline = time.time() + timeout
    while time.time() < deadline:
        try:
            if urllib.request.urlopen(BASE + '/login', timeout=3).status == 200:
                return
        except Exception:
            time.sleep(0.5)
    raise SystemExit('測試網站沒有啟動成功。')


def main():
    parser = argparse.ArgumentParser(description='瀏覽器端到端測試')
    parser.add_argument('--only', help='只跑名稱含這段文字的情境')
    parser.add_argument('--headed', action='store_true', help='顯示瀏覽器視窗')
    parser.add_argument('--keep-db', action='store_true', help='跑完不刪測試資料庫')
    args = parser.parse_args()

    try:
        import playwright  # noqa: F401
    except ImportError:
        raise SystemExit('還沒安裝 Playwright。請先執行：pip install playwright  （詳見這個檔案開頭的說明）')

    import scenarios
    chosen = [s for s in scenarios.SCENARIOS if not args.only or args.only in s.__name__]
    if not chosen:
        raise SystemExit('沒有符合的情境。可用的情境：' + '、'.join(s.__name__ for s in scenarios.SCENARIOS))

    env = dict(os.environ, DB_DATABASE=E2E_DB, APP_ENV='local', APP_DEBUG='false', APP_URL=BASE, APP_LOCALE='zh_TW',
               MAIL_MAILER='log', SESSION_DRIVER='file', CACHE_STORE='file', SESSION_LIFETIME='480', PHP_CLI_SERVER_WORKERS='4')

    print(f'1. 建立測試資料庫 {E2E_DB}')
    run(['php', 'tests/e2e/db.php', 'create', E2E_DB])
    server = None
    failures = []
    try:
        print('2. 建立資料表與示範資料（需要一點時間）')
        run(['php', 'artisan', 'migrate:fresh', '--seed', '--force'], env=env, quiet=True)

        print(f'3. 啟動測試網站 {BASE}')
        if platform.system() == 'Windows':
            # Windows 的 PHP 內建網站會隨機把回應截斷（樣式檔、頁面只送一半），測試會時好時壞；改用專案的穩定伺服器（tools/stable-server.py）。
            server = subprocess.Popen([sys.executable, str(ROOT / 'tools/stable-server.py'), '--port', str(PORT)], cwd=ROOT, env=env,
                                      stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        else:
            router = ROOT / 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
            # PHP 內建網站一次只處理一個請求；在 Linux／macOS 用 PHP_CLI_SERVER_WORKERS 開多個工作程序（上面的 env）。
            server = subprocess.Popen(['php', '-S', f'127.0.0.1:{PORT}', str(router)], cwd=ROOT / 'public', env=env,
                                      stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        wait_for_server()

        print('4. 開始執行情境\n')
        from playwright.sync_api import sync_playwright
        with sync_playwright() as p:
            # 假鏡頭：讓「掃描」視窗在沒有實體相機的測試機器上也能開（自動允許相機、提供假的影像）。
            camera_args = ['--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream']
            try:
                browser = p.chromium.launch(channel='chrome', headless=not args.headed, args=camera_args)
            except Exception:
                browser = p.chromium.launch(headless=not args.headed, args=camera_args)
            for scenario in chosen:
                started = time.time()
                try:
                    try:
                        scenario(browser, BASE, ROOT)
                    except Exception as first:  # noqa: BLE001
                        # 失敗時自動再跑一次：測試用的 PHP 內建網站（尤其在 Windows）偶發斷線，會讓檔案沒載入或逾時。
                        # 真正的程式問題每次都會失敗，所以重跑一次不會漏掉；只有兩次都失敗才算失敗。
                        print(f'  （{scenario.__name__} 第一次失敗，重跑一次：{str(first).strip().splitlines()[0][:80]}）')
                        scenario(browser, BASE, ROOT)
                    print(f'  通過  {scenario.__name__}  ({time.time() - started:.1f} 秒)  {scenario.__doc__.strip().splitlines()[0] if scenario.__doc__ else ""}')
                except Exception as exc:  # noqa: BLE001
                    failures.append(scenario.__name__)
                    print(f'  失敗  {scenario.__name__}  ({time.time() - started:.1f} 秒)')
                    print('        ' + str(exc).strip().replace('\n', '\n        ')[:1200])
                    if os.environ.get('E2E_TRACE'):
                        traceback.print_exc()
            browser.close()
    finally:
        if server:
            server.terminate()
            try:
                server.wait(10)
            except Exception:
                server.kill()
        if not args.keep_db:
            run(['php', 'tests/e2e/db.php', 'drop', E2E_DB], check=False, quiet=True)

    print(f'\n共 {len(chosen)} 個情境，失敗 {len(failures)} 個' + (f'：{"、".join(failures)}' if failures else '，全部通過'))
    sys.exit(1 if failures else 0)


if __name__ == '__main__':
    if platform.system() == 'Windows':
        os.environ.setdefault('PYTHONIOENCODING', 'utf-8')
        try:
            sys.stdout.reconfigure(encoding='utf-8')
        except Exception:
            pass
    main()
