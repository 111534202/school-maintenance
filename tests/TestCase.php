<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

// 【自動化測試是什麼？】用程式去操作系統（開頁面、送表單），再檢查結果對不對；改了程式之後執行 php artisan test，幾秒內就能知道有沒有把原本的功能弄壞。
// 每個以 test_ 開頭的方法就是一個測試；assertXxx 開頭的是「檢查」，檢查不過這個測試就失敗並指出哪裡不對。
// 測試用的資料庫是記憶體裡的 SQLite（見 phpunit.xml），每個測試跑完都會清空，不會動到你本機的真實資料。
// 這個檔案是所有測試的共同父類別，目前沒有額外設定。共用的建立身分與用戶的小工具在 tests/Concerns/InteractsWithRolesAndUsers.php。
abstract class TestCase extends BaseTestCase
{
    //
}
