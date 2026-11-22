"""
瀏覽器端到端測試的情境（由 tests/e2e/run.py 依序執行）。

每個情境是一個函式 scenario(browser, base, root)：開一個乾淨的瀏覽器分頁，照使用者的操作走完一個流程，
用 check() 檢查畫面上看得到的結果；任何一個 check 不成立就丟出錯誤、這個情境標記為失敗。
示範資料帳號（密碼都是 password）：admin 管理員、it_manager 資訊組主管、repairer 維修人員、teacher 教師、executive 主管。

想新增情境：寫一個函式、開頭用 @scenario 登記，照下面的寫法操作與檢查即可。
"""
import datetime
import os
import re
import tempfile
import time
import uuid
from contextlib import contextmanager

SCENARIOS = []
PASSWORD = 'password'


def scenario(fn):
    """登記一個情境（依登記順序執行）。"""
    SCENARIOS.append(fn)
    return fn


def no_js_errors(errors):
    """頁面不能有 JavaScript 錯誤。例外：同時有本機檔案載入失敗（連線被重設）時，錯誤多半是檔案沒載入造成的測試環境雜訊，不算失敗。"""
    if errors['js'] and not errors['net']:
        raise AssertionError('JavaScript 錯誤：' + str(errors['js']))


def check(condition, message):
    if not condition:
        raise AssertionError(message)


@contextmanager
def session(browser, base, width=1366, height=800, mobile=False, block_external=False):
    """開一個乾淨的瀏覽器環境，回傳 (page, errors)；errors 會收集畫面上的 JavaScript 錯誤與（選擇性）外部連線。"""
    context = browser.new_context(viewport={'width': width, 'height': height}, has_touch=mobile, is_mobile=mobile)
    context.set_default_timeout(20000)
    page = context.new_page()
    page.base = base
    errors = {'js': [], 'external': [], 'net': []}
    page.on('pageerror', lambda e: errors['js'].append(str(e)[:150]))
    # 本機檔案載入失敗（連線被重設）幾乎都是測試用 PHP 內建網站在 Windows 上的問題；記下來，判斷 JavaScript 錯誤時會參考。
    page.on('requestfailed', lambda r: errors['net'].append(r.url[-60:]) if r.url.startswith(base) else None)
    if block_external:
        def guard(route):
            if route.request.url.startswith((base, 'data:', 'blob:')):
                route.continue_()
            else:
                errors['external'].append(route.request.url)
                route.abort()
        context.route('**/*', guard)
    try:
        yield page, errors
    finally:
        context.close()


def settle(page, wait_ms=0):
    """等頁面可以操作：DOM 載入完成就繼續，load 事件最多再等 3 秒（PHP 內建網站偶爾會讓 load 拖很久，不影響操作）。"""
    page.wait_for_load_state('domcontentloaded')
    try:
        page.wait_for_load_state('load', timeout=3000)
    except Exception:  # noqa: BLE001
        pass
    if wait_ms:
        page.wait_for_timeout(wait_ms)


def goto(page, path, tries=4):
    """開網頁；測試用的 PHP 內建網站偶爾會斷線，所以失敗時重試。"""
    last = None
    for _ in range(tries):
        try:
            started = time.time()
            response = page.goto(page.base + path, wait_until='domcontentloaded', timeout=20000)
            if os.environ.get('E2E_DEBUG'):
                print(f'      [goto {path} {time.time() - started:.1f}s]')
            settle(page)
            return response
        except Exception as exc:  # noqa: BLE001
            last = exc
            page.wait_for_timeout(800)
    raise last


def login(page, account, password=PASSWORD):
    page.context.clear_cookies()
    goto(page, '/login')
    page.fill('#login', account)
    page.fill('#password', password)
    page.click('button[type=submit]')
    settle(page)


def body(page):
    return re.sub(r'\n\s*\n+', '\n', page.inner_text('body'))


def click_and_wait(page, selector, ms=1500):
    page.click(selector)
    settle(page, ms)


def write_temp(name, content):
    path = os.path.join(tempfile.mkdtemp(prefix='e2e_'), name)
    with open(path, 'w', encoding='utf-8', newline='') as handle:
        handle.write(content)
    return path


# ---------------------------------------------------------------- 登入與權限

@scenario
def login_and_navigation(browser, base, root):
    """登入、錯誤密碼提示、登出後再登入一律回主控台"""
    with session(browser, base) as (page, errors):
        goto(page, '/login')
        page.fill('#login', 'teacher'); page.fill('#password', 'wrong-password')
        page.click('button[type=submit]'); settle(page)
        check('/login' in page.url, '密碼錯誤時應停留在登入頁')

        login(page, 'teacher')
        check('/dashboard' in page.url, '登入後應在主控台，實際：' + page.url)

        goto(page, '/repairs')                       # 最後停留在報修頁
        page.context.clear_cookies()                  # 模擬登出後重新登入
        login(page, 'teacher')
        check(page.url.endswith('/dashboard'), '登出後重新登入應回主控台，不是上次的頁面：' + page.url)
        no_js_errors(errors)


@scenario
def permissions_by_role(browser, base, root):
    """不同身分看到的選單不同、沒權限的頁面顯示中文 403"""
    with session(browser, base) as (page, errors):
        # 選單裡的連結（用網址判斷，選單收合時文字看不到，但連結還在）。
        links = ('/users', '/mail-settings', '/backups', '/devices')
        login(page, 'admin')
        goto(page, '/dashboard')
        for link in links:
            check(page.locator(f'a[href$="{link}"]').count() > 0, f'管理員的選單應有 {link}')

        login(page, 'teacher')
        goto(page, '/dashboard')
        for link in links:
            check(page.locator(f'a[href$="{link}"]').count() == 0, f'教師的選單不應有 {link}')

        response = goto(page, '/users')
        check(response.status == 403, f'教師開用戶主檔應該是 403，實際 {response.status}')
        check('沒有權限' in body(page) or '403' in body(page), '403 頁應有中文說明')
        response = goto(page, '/no-such-page')
        check(response.status == 404, '不存在的頁面應該是 404')
        no_js_errors(errors)


# ---------------------------------------------------------------- 報修流程

@scenario
def repair_lifecycle(browser, base, root):
    """教師報修 → 主管派工 → 維修人員處理並用零件 → 教師驗收結案（零件庫存扣掉）"""
    with session(browser, base) as (page, errors):
        login(page, 'admin')
        goto(page, '/parts/1')
        before = re.search(r'目前庫存[^0-9]*(\d+)', body(page))
        check(before, '零件頁找不到「目前庫存」')
        stock_before = int(before.group(1))

        login(page, 'teacher')
        goto(page, '/repairs/create')
        page.fill('#device_note', 'E2E 投影機沒有畫面')
        page.fill('input[name="title"]', 'E2E 報修流程測試')
        page.fill('textarea[name="description"]', '開機後沒有畫面')
        click_and_wait(page, 'button:has-text("送出報修")')
        match = re.search(r'/repairs/(\d+)', page.url)
        check(match, '送出報修後應進到報修單頁：' + page.url)
        rid = match.group(1)

        login(page, 'it_manager')
        goto(page, f'/repairs/{rid}')
        page.select_option('select[name="assigned_to"]', label='維修人員')
        click_and_wait(page, 'button:has-text("確認派工")')
        check('已派工' in body(page), '派工後狀態應是已派工')

        login(page, 'repairer')
        goto(page, f'/repairs/{rid}')
        click_and_wait(page, 'button:has-text("開始處理")')
        check('處理中' in body(page), '開始處理後狀態應是處理中')

        goto(page, f'/repairs/{rid}/repair-logs/create')
        page.click('button:has-text("新增使用紀錄")')
        page.wait_for_timeout(400)
        page.click('#part_search_0')
        page.wait_for_timeout(300)
        page.click('#part_options_0 button:has-text("PART-1")')
        page.fill('#part_qty_0', '2')
        # 系統用台北時間（UTC+8）；測試機器（例如 CI）可能是 UTC，所以明確用台北時間，不能用機器的現在時間。
        now = datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=8)))
        page.fill('textarea[name="cause"]', '燈泡燒壞')
        page.fill('textarea[name="resolution"]', '更換燈泡')
        # 維修開始時間不能早於報修送出的時間：報修剛送出，所以從現在這一分鐘開始，2 分鐘後結束（結束時間可比現在晚不超過 5 分鐘）。
        page.fill('input[name="started_at"]', now.strftime('%Y-%m-%dT%H:%M'))
        page.fill('input[name="ended_at"]', (now + datetime.timedelta(minutes=2)).strftime('%Y-%m-%dT%H:%M'))
        click_and_wait(page, 'button:has-text("送出維修紀錄")', 2000)
        check('待驗收' in body(page), '送出維修紀錄後應進入待驗收：' + body(page)[:200])

        login(page, 'teacher')
        goto(page, f'/repairs/{rid}')
        click_and_wait(page, 'button:has-text("驗收通過")', 2000)
        check('已結案' in body(page), '驗收通過後應已結案')

        login(page, 'admin')
        goto(page, '/parts/1')
        after = int(re.search(r'目前庫存[^0-9]*(\d+)', body(page)).group(1))
        check(stock_before - after == 2, f'零件庫存應減少 2（{stock_before} → {after}）')
        no_js_errors(errors)


@scenario
def repair_photo_upload(browser, base, root):
    """手機拍的大照片（約 4000×3000）選進報修：自動縮小後附上，送出後報修單頁直接顯示縮圖"""
    with session(browser, base) as (page, errors):
        login(page, 'teacher')
        goto(page, '/repairs/create')
        page.fill('#device_note', 'E2E 照片測試的投影機')
        page.fill('input[name="title"]', 'E2E 照片上傳測試')
        page.fill('textarea[name="description"]', '機身有裂痕，附上照片')
        # 在瀏覽器裡畫一張 4000×3000 的雜訊圖（模擬手機照片，約 8 MB 以上），再像使用者選檔一樣放進附件欄位。
        page.evaluate("""async () => {
            const c = document.createElement('canvas'); c.width = 4000; c.height = 3000;
            const ctx = c.getContext('2d'); const data = ctx.createImageData(4000, 3000);
            for (let i = 0; i < data.data.length; i += 4) { data.data[i] = Math.random() * 255; data.data[i + 1] = Math.random() * 255; data.data[i + 2] = Math.random() * 255; data.data[i + 3] = 255; }
            ctx.putImageData(data, 0, 0);
            const blob = await new Promise(r => c.toBlob(r, 'image/jpeg', 0.95));
            window.__bigBytes = blob.size;
            const dt = new DataTransfer(); dt.items.add(new File([blob], 'IMG_0001.jpg', { type: 'image/jpeg' }));
            const input = document.getElementById('attachments'); input.files = dt.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }""")
        page.wait_for_timeout(4000)
        big = page.evaluate('window.__bigBytes')
        check(big > 5 * 1024 * 1024, f'測試用的大照片應該超過 5 MB（實際 {big} 位元組）')
        text = body(page)
        check('IMG_0001.jpg' in text, '縮小後的照片應出現在已選檔案清單：' + text[-300:])
        check('超過' not in text.split('已選')[-1][:200] and not page.locator('.file-error:visible').count(), '照片縮小後不應再跳出超過上限的錯誤')
        click_and_wait(page, 'button:has-text("送出報修")', 2500)
        match = re.search(r'/repairs/(\d+)', page.url)
        check(match, '送出報修後應進到報修單頁：' + page.url)
        page.wait_for_selector('img[alt="IMG_0001.jpg"]')
        width = page.evaluate("document.querySelector('img[alt=\"IMG_0001.jpg\"]').naturalWidth")
        check(width and width <= 1600, f'報修單頁應顯示縮圖，圖片寬度 {width}（縮小後最長邊不超過 1600）')
        no_js_errors(errors)


@scenario
def repair_double_submit_is_blocked(browser, base, root):
    """連點兩下「送出報修」只會建立一張報修單"""
    with session(browser, base) as (page, errors):
        login(page, 'teacher')
        goto(page, '/repairs/create')
        page.fill('#device_note', 'E2E 連點測試設備')
        page.fill('input[name="title"]', 'E2E 連點測試單')
        page.fill('textarea[name="description"]', '連點兩下')
        page.dblclick('button:has-text("送出報修")')
        settle(page)
        page.wait_for_timeout(2500)
        goto(page, '/repairs?keyword=E2E 連點測試單')
        count = page.locator('tbody tr', has_text='E2E 連點測試單').count() + page.locator('.tb-card, .card', has_text='E2E 連點測試單').count()
        check(count >= 1, '應該找得到這張報修單')
        check('E2E 連點測試單' in body(page), '搜尋應找到報修單')
        titles = re.findall(r'E2E 連點測試單', body(page))
        check(len(titles) <= 2, f'同一個標題出現 {len(titles)} 次，可能建立了重複的報修單')


# ---------------------------------------------------------------- 空間預約

@scenario
def reservation_flow(browser, base, root):
    """教師選教室與日期、用科系→年級→班級下拉送出申請，主管核准後狀態顯示已核准"""
    with session(browser, base) as (page, errors):
        day = datetime.date.today() + datetime.timedelta(days=3)
        while day.weekday() > 4:
            day += datetime.timedelta(days=1)

        login(page, 'teacher')
        goto(page, '/reservations')
        page.click('#classroom_search')
        page.fill('#classroom_search', 'IT-301')
        page.wait_for_timeout(500)
        page.click('#classroom_options button[data-label*="IT-301"]')
        # 選好教室後系統會先用預設日期查一次課表；要等那次查完再改日期，否則查詢中的按鈕不能按，新日期就不會重新查詢。
        page.wait_for_selector('td.table-success, td.table-secondary', timeout=20000)
        page.fill('#reservation_date', day.isoformat())
        page.wait_for_timeout(3000)                 # 選好教室與日期會自動顯示課表
        slots = page.locator('td.table-success')
        check(slots.count() > 0, '應該有可申請的空堂')
        slots.last.click()                          # 選最後一個空堂（一定是還沒過去的時段，過去的時段不能核准）
        page.wait_for_timeout(400)
        page.click('button:has-text("下一步")')
        page.wait_for_timeout(500)

        major = page.locator('select[name="major_id"]')
        check(major.count() == 1, '應有科系下拉選單')
        major.select_option(index=1)
        page.locator('select[name="class_grade"]').select_option(index=1)
        page.locator('select[name="class_section"]').select_option(index=1)
        page.fill('textarea[name="purpose"], input[name="purpose"]', 'E2E 預約測試')
        click_and_wait(page, 'button:has-text("送出預約申請")', 2000)
        check('/reservations' in page.url and '/create' not in page.url, '送出後應回到預約列表：' + page.url)
        check('待審核' in body(page), '剛送出的申請應顯示待審核')

        login(page, 'it_manager')
        goto(page, '/reservations')
        page.locator('tbody tr', has_text='待審核').first.click()
        page.wait_for_timeout(2000)
        frame = page.frame_locator('iframe').first
        frame.locator('input[type=radio][value="approved"]').first.check(force=True)
        frame.locator('button:has-text("送出審核")').click()
        frame.locator('button:has-text("確認送出")').click()   # 送出前會先跳出「請再確認一次」
        page.wait_for_timeout(3000)
        goto(page, '/reservations')
        check('已核准' in body(page), '審核後列表應顯示已核准：' + body(page)[-600:].replace(chr(10), ' | '))
        no_js_errors(errors)


# ---------------------------------------------------------------- 保養

@scenario
def maintenance_order_flow(browser, base, root):
    """主管指派保養工單負責人，只有負責人能回報；NG 會轉成報修"""
    with session(browser, base) as (page, errors):
        login(page, 'it_manager')
        goto(page, '/maintenance-orders')
        check('負責人' in body(page), '保養工單列表應有負責人欄')
        # 挑「還沒完成」的第一張工單（不寫死工單編號）：這個情境會改資料，第一次跑到一半失敗後自動重跑時，
        # 上一輪已經回報完的工單不能再用，改挑下一張待處理的，重跑才有機會過。
        goto(page, '/maintenance-orders?status=pending')
        href = page.locator('tr[data-modal-frame-href]').first.get_attribute('data-modal-frame-href')
        check(href and '/maintenance-orders/' in href, '應有待處理的保養工單可以用：' + body(page)[:200])
        order_path = '/maintenance-orders/' + href.split('/maintenance-orders/', 1)[1].split('?')[0].strip('/')
        goto(page, order_path)
        page.select_option('select[name="assigned_to"]', label='維修人員')
        click_and_wait(page, 'button:has-text("指派")')

        login(page, 'repairer')
        response = goto(page, order_path + '/result/create')
        check(response.status == 200, f'負責人應能開回報表單，實際 {response.status}')
        page.check('#result_ng')
        page.fill('#note', 'E2E 管線漏水')
        page.click('button:has-text("送出")')
        # 等到結果出現再檢查（最多 20 秒），不要只等固定幾秒：測試用的網站在 CI 上偶爾比較慢。
        try:
            page.wait_for_function("() => document.body.innerText.includes('已自動建立報修單') || document.body.innerText.includes('已轉報修')", timeout=20000)
        except Exception:  # noqa: BLE001
            pass
        settle(page)
        check('已自動建立報修單' in body(page) or '已轉報修' in body(page), 'NG 回報後應轉成報修：' + body(page)[:200])

        login(page, 'it_manager')
        goto(page, order_path)
        check('已轉報修' in body(page), '工單狀態應顯示已轉報修')
        no_js_errors(errors)


# ---------------------------------------------------------------- 批次匯入與 QR 貼紙

@scenario
def bulk_import_devices_and_qr_sheet(browser, base, root):
    """批次匯入設備：預覽標出錯誤列、確認後寫入，再列印這批設備的 QR 貼紙"""
    uid = uuid.uuid4().hex[:6].upper()          # 每次執行用不同編號，重跑時才不會因為「編號已存在」失敗
    csv = ('設備編號*,資產編號,類別*,品牌,型號,序號,保固到期日,教室*,狀態,核心設備\n'
           f'E2E-{uid}-001,,投影機,Epson,EB-X,SN1,2027-12-31,IT-301,正常,否\n'
           f'E2E-{uid}-002,,投影機,Epson,EB-Y,SN2,,IT-301,,\n'
           f'E2E-{uid}-BAD,,不存在的類別,,,,,IT-301,,\n')
    path = write_temp('devices.csv', '﻿' + csv)
    with session(browser, base) as (page, errors):
        login(page, 'admin')
        goto(page, '/devices')
        click_and_wait(page, 'a:has-text("批次匯入")')
        check('/import/devices' in page.url, '應進到匯入頁')
        page.set_input_files('input[type=file]', path)
        click_and_wait(page, 'button:has-text("上傳並預覽")')
        text = body(page)
        check('可匯入 2 列' in text and '有問題 1 列' in text, '預覽應顯示 2 列可匯入、1 列有問題：' + text[:300])
        check('找不到類別' in text, '應說明錯誤原因')
        page.click('button:has-text("確認匯入 2 筆")')          # 有錯誤的列會被略過，所以會先跳出確認視窗
        page.wait_for_selector('#confirmActionDialog[open]')
        page.click('#confirmActionOk')
        settle(page)
        page.wait_for_timeout(2000)
        check('已匯入 2 筆設備' in body(page), '應顯示匯入成功訊息：' + body(page)[:300])

        goto(page, f'/devices?keyword=E2E-{uid}')
        listed = body(page)
        check(f'E2E-{uid}-001' in listed and f'E2E-{uid}-002' in listed and f'E2E-{uid}-BAD' not in listed, '設備列表應有 2 台新設備、沒有錯誤那台')

        goto(page, f'/devices/qr-sheet?keyword=E2E-{uid}')
        sheet = body(page)
        check(f'E2E-{uid}-001' in sheet and f'E2E-{uid}-002' in sheet, 'QR 貼紙頁應有兩台設備的編號')
        check(page.locator('.label svg').count() == 2, '每張貼紙都應有 QR Code')

        # 匯錯了可以整批復原：回到匯入頁，按最新一批的「整批復原」。
        goto(page, '/import/devices')
        check('最近的匯入紀錄' in body(page) and '成功 2、略過 1' in body(page), '匯入頁應列出剛剛那一批')
        page.click('button:has-text("整批復原")')
        page.wait_for_selector('#confirmActionDialog[open]')
        page.click('#confirmActionOk')
        settle(page)
        page.wait_for_timeout(2000)
        check('已復原這一批匯入' in body(page), '應顯示復原成功訊息：' + body(page)[:300])
        goto(page, f'/devices?keyword=E2E-{uid}')
        check(f'E2E-{uid}-001' not in body(page), '復原後設備列表不該再有這批設備')
        no_js_errors(errors)


@scenario
def bulk_import_users_forces_password_change(browser, base, root):
    """批次匯入用戶：用初始密碼登入後一定要先設定新密碼"""
    uid = uuid.uuid4().hex[:6]
    account = f'e2e.{uid}'
    csv = ('姓名*,帳號*,Email*,電話,身分*,部門,初始密碼,啟用\n'
           f'E2E匯入教師,{account},{account}@school.test,,教師／教室管理人,,Init-Pass-9999,是\n')
    path = write_temp('users.csv', '﻿' + csv)
    with session(browser, base) as (page, errors):
        login(page, 'admin')
        goto(page, '/users')
        click_and_wait(page, 'a:has-text("批次匯入")')
        page.set_input_files('input[type=file]', path)
        click_and_wait(page, 'button:has-text("上傳並預覽")')
        text = body(page)
        check('可匯入 1 列' in text, '預覽應有 1 列可匯入：' + text[:300])
        check('Init-Pass-9999' not in page.content(), '預覽畫面不應顯示初始密碼')
        click_and_wait(page, 'button:has-text("確認匯入 1 筆")', 2000)
        check('已匯入 1 筆用戶' in body(page), '應顯示匯入成功：' + body(page)[:300])

        login(page, account, 'Init-Pass-9999')
        check('/password/change' in page.url, '第一次登入應被導到設定新密碼：' + page.url)
        goto(page, '/repairs')
        check('/password/change' in page.url, '設定新密碼前哪裡都不能去：' + page.url)
        no_js_errors(errors)


# ---------------------------------------------------------------- 寄信設定

@scenario
def mail_settings_page(browser, base, root):
    """寄信設定：選預設自動帶入、儲存後密碼不會出現在畫面原始碼"""
    with session(browser, base) as (page, errors):
        login(page, 'admin')
        goto(page, '/mail-settings')
        page.click('[data-preset=gmail]')
        check(page.input_value('#host') == 'smtp.gmail.com' and page.input_value('#port') == '587', 'Gmail 預設應帶入伺服器與連接埠')
        page.fill('#smtp_account', 'school@example.edu')
        page.fill('#password', 'Secret-App-Pass-123')
        page.fill('#from_address', 'school@example.edu')
        click_and_wait(page, 'button:has-text("儲存設定")')
        check('已儲存' in body(page), '應顯示已儲存')
        check('Secret-App-Pass-123' not in page.content(), '密碼不應出現在畫面原始碼')
        check(page.locator('button:has-text("寄測試信")').is_enabled(), '儲存後應能寄測試信')

        login(page, 'it_manager')
        check(goto(page, '/mail-settings').status == 403, '資訊組主管不能開寄信設定')
        no_js_errors(errors)


# ---------------------------------------------------------------- 離線、手機、安全

@scenario
def works_without_internet(browser, base, root):
    """封鎖所有外部網站後，樣式、圖示、圖表仍正常（外部套件都在專案內）"""
    with session(browser, base, block_external=True) as (page, errors):
        login(page, 'admin')
        goto(page, '/dashboard')
        page.wait_for_timeout(1500)
        check(page.evaluate("()=>typeof window.bootstrap !== 'undefined'"), 'Bootstrap 腳本沒有載入')
        check(page.evaluate("()=>typeof window.Chart !== 'undefined'"), 'Chart.js 沒有載入')
        check(page.evaluate("()=>document.fonts.check('1em bootstrap-icons')"), '圖示字型沒有載入')
        check(not errors['external'], '有連到外部網站：' + str(errors['external'][:3]))
        no_js_errors(errors)


@scenario
def mobile_pages_do_not_overflow(browser, base, root):
    """手機寬度（375）下主要頁面不會左右溢出、沒有 JavaScript 錯誤"""
    paths = ['/dashboard', '/repairs', '/repairs/create', '/reservations', '/maintenance-orders', '/devices', '/users', '/parts', '/knowledge-base']
    with session(browser, base, width=375, height=812, mobile=True) as (page, errors):
        login(page, 'admin')
        bad = []
        for path in paths:
            goto(page, path)
            page.wait_for_timeout(300)
            measure = '()=>document.documentElement.scrollWidth - document.documentElement.clientWidth'
            overflow = page.evaluate(measure)
            if overflow > 2:
                page.wait_for_timeout(2500)             # 樣式檔還沒載入完時畫面會暫時很寬：等一下再量一次，避免誤判
                overflow = page.evaluate(measure)
            if overflow > 2:
                bad.append(f'{path}（溢出 {overflow}px）')
        check(not bad, '手機版面溢出：' + '、'.join(bad))
        no_js_errors(errors)


@scenario
def security_headers_present(browser, base, root):
    """每個頁面都有基本安全標頭"""
    with session(browser, base) as (page, errors):
        response = goto(page, '/login')
        headers = response.headers
        check(headers.get('x-frame-options') == 'SAMEORIGIN', '缺少 X-Frame-Options')
        check(headers.get('x-content-type-options') == 'nosniff', '缺少 X-Content-Type-Options')
        check('camera=(self)' in headers.get('permissions-policy', ''), '缺少 Permissions-Policy')
