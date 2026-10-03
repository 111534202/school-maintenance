# 學校設備維保電子化系統

校園設備的自助除錯、報修、派工、維修與驗收，以及教室、設備、用戶等主檔管理。
以 Laravel 13（PHP 8.4）、MySQL 8.4、Bootstrap 5.3 與 Chart.js 4 開發。

> 四位組員使用同一個 Git Repository，各自使用本機的 MySQL，資料表結構只透過 migration 同步、測試資料只透過 seeder 同步。
> 不要把 `.env`、`vendor`、`node_modules` 或真實密碼提交到 Git。

## 功能模組

| 模組 | 說明 | 主要負責 |
|---|---|---|
| 自助知識庫、報修、派工、維修紀錄、驗收 | 設備 QR／編號 → 知識庫 → 報修 → 派工 → 維修 → 待驗收 → 結案（驗收不通過退回處理中） | 彭仕衡 |
| 教室、設備、設備類別主檔、QR Code | 設備狀態與核心設備旗標、教室設備異常標記 | 林政寬 |
| 用戶、身分（權限）、部門主檔 | 身分像 Discord 身分組，勾選要開放的權限 | — |
| 主控台、通知鈴鐺、操作紀錄 | 數字卡片與圖表（點擊跳到對應功能區）、待辦通知、稽核紀錄 | — |

## 第一次安裝

需要：PHP 8.4（含 pdo_mysql）、Composer 2、MySQL 8.4。

```bash
composer install
cp .env.example .env
php artisan key:generate
```

1. 在本機 MySQL 建立資料庫 `school_maintenance`（字元集 utf8mb4）。
2. 編輯 `.env`，在 `DB_PASSWORD` 填入自己的 MySQL 密碼（`.env` 不會被提交）。
3. 建立資料表並寫入示範資料：

```bash
php artisan migrate:fresh --seed
```

4. 啟動網站，開啟 <http://127.0.0.1:8000>：

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

`migrate:fresh` 會**清空整個資料庫**再重建，只在本機開發使用。已經有資料、只想套用新的資料表變更時，用 `php artisan migrate`。

## 示範帳號

登入頁用「帳號名稱」或 Email 登入。示範資料裡所有帳號的密碼都是 `password`（只是示範用，正式使用前務必更換）。

| 帳號名稱 | 身分 | 能做的事 |
|---|---|---|
| `admin` | 系統管理員 | 全部權限 |
| `it_manager` | 資訊組主管 | 教室／設備／設備類別主檔、派工、查看操作紀錄、管理知識庫、接收派工通知副本 |
| `repairer` | 維修人員 | 提出報修；處理「指派給自己」的案件（示範資料裡沒有指派給他的案件，看板是空的） |
| `teacher` | 教師 | 提出報修；驗收「自己報修」的案件（示範案件除了保養 NG 轉入的那張，報修人都是這個帳號） |
| `executive` | 主管 | 提出報修、派工、看全部案件；驗收自己報修的案件 |

要看完整的維修流程，可以用 `admin` 登入，或到報修單詳細頁把案件派給維修人員。

## 權限與資料範圍

- 每個身分可以做哪些事，由「身分主檔」勾選的權限決定（權限清單在 `app/Support/PermissionCatalog.php`）；系統管理員永遠擁有全部權限。
- 報修單另外有「這一張單是不是你的」的限制（`app/Policies/RepairRequestPolicy.php`）：
  能派工的人與管理員看全部；其他人只看自己報修或被指派的案件；
  只有被指派的維修人員能開始處理與填維修紀錄；只有報修人能驗收或退回。
  這個資料範圍規則依規格書的角色說明整理，**細部規則仍列在待確認事項**，要調整只需要改這一個檔案。
- 附件（照片、影片）存在私有磁碟，只能透過網站下載，並套用同樣的檢視權限。
- 帳號被停用後，下一個請求就會被強制登出；登入失敗太多次會暫時鎖定（見 `LoginController`）。

## 常用指令

```bash
php artisan test                      # 執行全部自動化測試（使用記憶體中的 SQLite，不會動到本機資料庫）
php artisan migrate:fresh --seed      # 重建資料庫並寫入示範資料（會清空資料）
php artisan view:clear                # 清除畫面快取（改了畫面卻沒更新時用）
```

## 其他注意事項

- 畫面樣式（Bootstrap、圖示）與圖表（Chart.js）、相機掃描函式庫是從網路 CDN 載入，沒有網路時畫面會沒有樣式與圖表。
- 派工通知信目前寫入 `storage/logs/laravel.log`，不會真的寄出（`.env` 的 `MAIL_MAILER=log`）；要寄信只需要改 `.env`。
- 語言切換選單預設隱藏；中英文翻譯都在 `lang/`，要開放把 `.env` 的 `APP_LOCALE_SWITCHER` 設成 `true`。
- 系統時區預設是台北（`APP_TIMEZONE=Asia/Taipei`）。
- 設備狀態與報修流程的自動連動規則（案件處理中是否自動把設備標成維修中）尚未確認，目前沒有串接，見 `docs/待確認/`。

## 文件

- `docs/repair-module-功能說明.md`：報修模組操作步驟、角色、狀態流程與跨模組接口。
- `docs/repair-module-schema-notes.md`：報修相關資料表與設計說明。
- `docs/跨組待確認事項.md`、`docs/待確認/`：需要其他組員或指導老師確認的事項。
