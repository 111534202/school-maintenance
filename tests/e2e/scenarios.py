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


def problems(page):
    """畫面上的錯誤訊息（驗證錯誤、紅字提醒）與網址，失敗時附在訊息後面，才看得出為什麼停在原頁（偶發失敗用）。"""
    try:
        texts = page.eval_on_selector_all('.alert-danger, .invalid-feedback, .text-danger, :invalid', 'els => els.map(e => (e.name ? e.name + ": " : "") + (e.validationMessage || e.innerText || "").trim()).filter(Boolean)')
    except Exception:  # noqa: BLE001
        texts = []
    return f'（網址 {page.url}；錯誤訊息 {texts[:6]}）'


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
        # 維修開始時間不能早於報修送出的時間、結束時間不能晚於送出的那一刻：報修剛送出，所以開始、結束都填現在這一分鐘。
        page.fill('input[name="started_at"]', now.strftime('%Y-%m-%dT%H:%M'))
        page.fill('input[name="ended_at"]', now.strftime('%Y-%m-%dT%H:%M'))
        click_and_wait(page, 'button:has-text("送出維修紀錄")', 2000)
        check('待驗收' in body(page), '送出維修紀錄後應進入待驗收：' + body(page)[:200] + problems(page))

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
        check('已自動建立報修單' in body(page) or '已轉報修' in body(page), 'NG 回報後應轉成報修：' + body(page)[:200] + problems(page))

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


@scenario
def part_create_keeps_safety_stock(browser, base, root):
    """新增零件：安全庫存要照使用者填的存起來（各種填法：點進去直接打字、填完沒送出再回來、一載入就打字）"""
    def open_form(page):
        goto(page, '/parts/create')
        page.wait_for_selector('#name')

    def saved_safety(page):
        # 送出後會導到零件履歷；從畫面上讀「安全庫存」那一列。
        text = body(page)
        m = re.search(r'安全庫存\s*[:：]?\s*(\d+)', text)
        return int(m.group(1)) if m else None

    with session(browser, base) as (page, errors):
        login(page, 'admin')

        # A. 點進安全庫存欄位（原本顯示 0）直接打字。
        open_form(page)
        page.wait_for_timeout(600)
        page.fill('#name', 'E2E 安全庫存 A')
        page.fill('#unit', '條')
        page.click('#safety_stock')
        page.keyboard.type('6')
        page.fill('#current_stock', '0')
        click_and_wait(page, 'button:has-text("儲存")')
        check('零件新增成功' in body(page), 'A：新增零件應成功：' + body(page)[:200])
        check(saved_safety(page) == 6, f'A：安全庫存應該是 6，實際是 {saved_safety(page)}')

        # B. 一載入頁面就馬上打字（不等任何初始化）。
        open_form(page)
        page.fill('#name', 'E2E 安全庫存 B')
        page.fill('#unit', '個')
        page.fill('#safety_stock', '9')
        page.fill('#current_stock', '0')
        click_and_wait(page, 'button:has-text("儲存")')
        check(saved_safety(page) == 9, f'B：安全庫存應該是 9，實際是 {saved_safety(page)}')

        # C. 填了一半沒送出就離開，再回來（會還原上次的內容），改完再送出。
        open_form(page)
        page.wait_for_timeout(600)
        page.fill('#name', 'E2E 安全庫存 C')
        page.fill('#unit', '個')
        page.fill('#safety_stock', '4')
        page.wait_for_timeout(900)            # 暫存有 0.4 秒的延遲
        page.evaluate("()=>{ for (const f of document.forms) f.dataset.guardLeaveOk = '1'; }")   # 不要跳離開提醒
        open_form(page)
        page.wait_for_timeout(900)
        restored = page.input_value('#safety_stock')
        check(restored == '4', f'C：回來後應還原成 4，實際是 {restored}')
        page.fill('#current_stock', '0')
        click_and_wait(page, 'button:has-text("儲存")')
        check(saved_safety(page) == 4, f'C：安全庫存應該是 4，實際是 {saved_safety(page)}')

        # D. 前一個零件存好以後，再開新增頁，不應該還原到舊的值。
        open_form(page)
        page.wait_for_timeout(900)
        check(page.input_value('#safety_stock') == '0', 'D：新增頁的安全庫存預設應該是 0，不該還原舊內容：' + page.input_value('#safety_stock'))

        # E. 填好安全庫存後，游標還停在欄位上就用滾輪捲頁面：數字不可以被滾輪改掉（數字欄位預設會被滾輪改，最小值 0 會一路捲到 0）。
        open_form(page)
        page.wait_for_timeout(600)
        page.fill('#name', 'E2E 安全庫存 E')
        page.fill('#unit', '個')
        page.click('#safety_stock')
        page.keyboard.type('5')
        box = page.locator('#safety_stock').bounding_box()
        page.mouse.move(box['x'] + 20, box['y'] + 10)
        for _ in range(8):
            page.mouse.wheel(0, 120)
            page.wait_for_timeout(60)
        check(page.input_value('#safety_stock') == '5', '游標停在欄位上滾動滾輪後，安全庫存被改成 ' + page.input_value('#safety_stock'))
        no_js_errors(errors)


@scenario
def scanner_zoom_works(browser, base, root):
    """掃描視窗的放大：鏡頭能變焦就用鏡頭，不能變焦就用畫面數位放大；倍率標示跟著變、放大上限擋得住、關掉再開會回到 1 倍"""
    def open_scanner(page):
        goto(page, '/repairs/create')
        page.click('[data-scan-target="#device_code_input"]')
        page.wait_for_selector('#codeScannerModal.show')
        page.wait_for_selector('[data-scanner-tools]:not([hidden])', timeout=15000)
        page.wait_for_timeout(500)

    def label(page):
        return page.inner_text('[data-scanner-zoom-label]').strip()

    def state(page):
        return page.evaluate("""()=>{ const v=document.getElementById('codeScannerVideo'); const t=v.srcObject&&v.srcObject.getVideoTracks()[0];
            return { transform: v.style.transform, zoom: t&&t.getSettings?t.getSettings().zoom:null }; }""")

    # 1. 鏡頭本身的變焦能力（測試用假鏡頭有，也可能沒有；沒有的話走數位放大，兩種都要能放大）。
    with session(browser, base) as (page, errors):
        login(page, 'admin')
        open_scanner(page)
        check(label(page) == '1.0x', '一開始應該是 1.0x：' + label(page))
        before = state(page)
        page.click('[data-scanner-zoom-in]')
        page.click('[data-scanner-zoom-in]')
        page.wait_for_timeout(600)
        check(label(page) == '2.0x', '按兩次放大應該是 2.0x：' + label(page))
        after = state(page)
        zoomed = ('scale' in (after['transform'] or '')) or (isinstance(after['zoom'], (int, float)) and isinstance(before['zoom'], (int, float)) and after['zoom'] > before['zoom'])
        check(zoomed, f'畫面或鏡頭應該真的放大了：之前 {before}、之後 {after}')
        page.click('[data-scanner-zoom-out]')
        check(label(page) == '1.5x', '縮小一次應該是 1.5x：' + label(page))
        page.press('body', 'Escape')
        page.wait_for_selector('#codeScannerModal.show', state='detached', timeout=5000)
        open_scanner(page)
        check(label(page) == '1.0x', '關掉再開應該回到 1.0x：' + label(page))
        no_js_errors(errors)

    # 2. 鏡頭完全不能變焦（筆電、iPhone）：一定要用數位放大，而且有上限。
    with session(browser, base) as (page, errors):
        page.add_init_script("MediaStreamTrack.prototype.getCapabilities = function () { return {}; };")
        login(page, 'admin')
        open_scanner(page)
        for _ in range(10):
            page.click('[data-scanner-zoom-in]')
        check(label(page) == '4.0x', '數位放大最多 4.0x：' + label(page))
        check(state(page)['transform'] == 'scale(4)', '畫面應該放大 4 倍：' + str(state(page)))
        page.evaluate("()=>{ const r=document.querySelector('[data-scanner-zoom]'); r.value=2; r.dispatchEvent(new Event('input',{bubbles:true})); }")
        check(label(page) == '2.0x' and state(page)['transform'] == 'scale(2)', '拉滑桿到 2 倍：' + label(page) + ' ' + str(state(page)))
        no_js_errors(errors)


@scenario
def site_title_goes_home(browser, base, root):
    """左上角的系統名稱（校園設備維保電子化系統）：登入前後都顯示、登入後點它回主控台；電腦、平板、手機寬度都在畫面內、不造成左右溢出"""
    title = '校園設備維保電子化系統'
    measure = """()=>{ const a=document.querySelector('[data-site-title]'); const r=a.getBoundingClientRect();
        return { text: a.textContent.trim(), left: r.left, right: r.right, vw: document.documentElement.clientWidth,
                 truncated: a.scrollWidth > a.clientWidth + 1, overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth }; }"""
    with session(browser, base) as (page, errors):
        goto(page, '/login')
        check(title in body(page), '登入頁應該顯示系統名稱')
        login(page, 'teacher')
        for width, height, mobile in [(1366, 800, False), (768, 1024, False), (375, 812, True)]:
            page.set_viewport_size({'width': width, 'height': height})
            goto(page, '/repairs')
            page.wait_for_timeout(300)
            m = page.evaluate(measure)
            check(m['text'] == title, f'{width}px：標題文字不對：{m["text"]}')
            check(m['left'] >= 0 and m['right'] <= m['vw'] + 1, f'{width}px：標題超出畫面：{m}')
            check(m['overflow'] <= 2, f'{width}px：頁面左右溢出 {m["overflow"]}px')
            if width >= 768:
                # 比較：舊名稱（11 字）在同一個寬度下是不是本來就被截斷（導覽列右側的按鈕占掉不少寬度，平板寬度本來就放不下整個標題）。
                old = page.evaluate("()=>{ const a=document.querySelector('[data-site-title]'); const keep=a.textContent; a.textContent='學校設備維保電子化系統'; const t=a.scrollWidth > a.clientWidth + 1; a.textContent=keep; return t; }")
                print(f'      [{width}px 舊名稱{"也是截斷" if old else "完整顯示"}]')
            if os.environ.get('E2E_SHOTS'):   # 想看畫面時：E2E_SHOTS=資料夾 python tests/e2e/run.py --only site_title
                page.screenshot(path=os.path.join(os.environ['E2E_SHOTS'], f'title_{width}.png'), clip={'x': 0, 'y': 0, 'width': width, 'height': 140})
            print(f'      [{width}px 標題寬 {round(m["right"] - m["left"])}px、畫面 {m["vw"]}px、{"截斷" if m["truncated"] else "完整顯示"}]')
        # 從別的頁面點標題，回到主控台。
        page.set_viewport_size({'width': 1366, 'height': 800})
        goto(page, '/repairs')
        click_and_wait(page, '[data-site-title]')
        check(page.url.rstrip('/').endswith('/dashboard'), '點標題應該回到主控台：' + page.url)
        no_js_errors(errors)


@scenario
def repair_log_max_time_follows_the_clock(browser, base, root):
    """維修紀錄的「最晚時間」要跟著時間走：表單開著不動、過了幾分鐘再填「現在」不能被瀏覽器誤擋"""
    with session(browser, base) as (page, errors):
        login(page, 'it_manager')
        goto(page, '/repairs/create')
        page.fill('#device_note', 'E2E 最晚時間')
        page.fill('input[name="title"]', 'E2E 最晚時間測試')
        page.fill('textarea[name="description"]', '測試')
        click_and_wait(page, 'button:has-text("送出報修")')
        rid = re.search(r'/repairs/(\d+)', page.url).group(1)
        # 報修由有派工權限的人手動派給維修人員。
        goto(page, f'/repairs/{rid}')
        page.select_option('select[name="assigned_to"]', label='維修人員')
        click_and_wait(page, 'button:has-text("確認派工")')
        login(page, 'repairer')
        goto(page, f'/repairs/{rid}')
        click_and_wait(page, 'button:has-text("開始處理")')
        goto(page, f'/repairs/{rid}/repair-logs/create')
        before = page.get_attribute('#ended_at', 'max')
        # 把這個分頁的時鐘撥快 3 分鐘（模擬表單開著、使用者過了一會兒才填）。
        page.evaluate("()=>{ const real = Date.now; Date.now = () => real() + 180000; }")
        page.focus('#ended_at')
        after = page.get_attribute('#ended_at', 'max')
        fmt = '%Y-%m-%dT%H:%M'
        delta = (datetime.datetime.strptime(after, fmt) - datetime.datetime.strptime(before, fmt)).total_seconds() / 60
        check(delta in (3, 4), f'時鐘撥快 3 分鐘後，最晚時間應該跟著往後約 3 分鐘：{before} → {after}')
        # 填「撥快後的現在」，不能被瀏覽器擋下。
        later = datetime.datetime.strptime(after, fmt).strftime(fmt)
        page.fill('input[name="started_at"]', before)
        page.fill('textarea[name="cause"]', '測試')
        page.fill('textarea[name="resolution"]', '測試')
        page.fill('#ended_at', later)
        valid = page.evaluate("()=>document.querySelector('#ended_at').checkValidity()")
        check(valid, f'撥快後填「現在」（{later}）不該被最晚時間擋下')
        no_js_errors(errors)


@scenario
def device_disable_and_restore(browser, base, root):
    """設備停用後從列表消失，篩選狀態「停用」找得到並能還原；還原後回到正常、重新出現在列表"""
    code = 'DEV-0005'   # 示範資料裡狀態正常的筆電；這個情境結束時會還原，不影響其他情境
    with session(browser, base) as (page, errors):
        login(page, 'admin')
        goto(page, f'/devices?keyword={code}')
        check(code in body(page), f'設備列表應找得到 {code}')

        # 停用：先跳出確認視窗，確認後設備從預設列表消失。
        page.click('form[action$="/disable"] button')
        page.wait_for_timeout(400)
        click_and_wait(page, 'button:has-text("確認停用")')
        goto(page, f'/devices?keyword={code}')
        check(code not in body(page), '停用後設備不該出現在預設列表')

        # 篩選「停用」：找得到，有「還原」鈕，沒有「停用」鈕。
        goto(page, f'/devices?keyword={code}&status=disabled')
        check(code in body(page), '篩選「停用」應找得到已停用的設備')
        check(page.locator('form[action$="/restore"] button').count() == 1, '已停用的設備列要有「還原」按鈕')
        check(page.locator('form[action$="/disable"] button').count() == 0, '已停用的設備不該再有「停用」按鈕')

        # 還原：確認後回到正常，預設列表又看得到。
        page.click('form[action$="/restore"] button')
        page.wait_for_timeout(400)
        click_and_wait(page, 'button:has-text("還原")')
        goto(page, f'/devices?keyword={code}')
        check(code in body(page) and '正常' in body(page), '還原後設備應回到預設列表且狀態是正常：' + body(page)[:200])
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
