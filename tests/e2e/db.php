<?php

/**
 * 瀏覽器測試用的小工具：建立／刪除「專用的測試資料庫」。
 * 用法：php tests/e2e/db.php create|drop <資料庫名稱>
 * 連線資訊讀專案的 .env（主機、連接埠、帳號、密碼）。為了安全，資料庫名稱必須以「_e2e」結尾，
 * 而且不能是 .env 裡設定的正式（開發）資料庫，避免誤刪真正的資料。
 */
require __DIR__ . '/../../vendor/autoload.php';

[, $action, $name] = array_pad($argv, 3, null);

if (! in_array($action, ['create', 'drop'], true) || ! $name || ! preg_match('/^[a-z0-9_]+_e2e$/', $name)) {
    fwrite(STDERR, "用法：php tests/e2e/db.php create|drop <名稱_e2e>\n");
    exit(2);
}

$env = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
$env->safeLoad();

if (($_ENV['DB_DATABASE'] ?? null) === $name) {
    fwrite(STDERR, "拒絕執行：{$name} 是 .env 設定的資料庫。\n");
    exit(2);
}

$dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $_ENV['DB_HOST'] ?? '127.0.0.1', $_ENV['DB_PORT'] ?? '3306');
$pdo = new PDO($dsn, $_ENV['DB_USERNAME'] ?? 'root', $_ENV['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
if ($action === 'create') {
    $pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
echo ($action === 'create' ? '已建立' : '已刪除') . "測試資料庫 {$name}\n";
