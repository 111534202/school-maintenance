<?php

namespace App\Services\Import;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * 用戶批次匯入。欄位：姓名*、帳號*、Email*、電話、身分*（身分名稱或代碼）、部門（部門名稱）、初始密碼、啟用。
 * 規則和「新增用戶」表單一樣（帳號 3～30 字英數與 . _ -、帳號與 Email 不可重複、身分與部門要已存在）。
 *
 * 密碼處理：匯入的帳號一律標記「第一次登入一定要先設定新密碼」。
 * - 有填「初始密碼」（至少 8 碼）：用它登入，登入後被要求改密碼。
 * - 沒填：系統給一組沒有人知道的隨機密碼，本人在登入頁按「忘記密碼」，用 Email 收臨時密碼再設定新密碼。
 * 密碼不會寫進操作紀錄，也不會留在畫面上。
 */
class UserImporter extends BulkImporter
{
    /** 每個密碼要雜湊（約 0.2 秒），人數太多會等很久，所以上限比設備低。 */
    public const MAX_ROWS = 200;

    public function type(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return '用戶';
    }

    public function permission(): string
    {
        return 'users.manage';
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => '姓名', 'required' => true, 'example' => '王小明', 'note' => '最多 255 字。'],
            ['key' => 'account', 'label' => '帳號', 'required' => true, 'example' => 'wang.xm', 'note' => '3～30 字，只能用英文、數字與 . _ -；不可重複（含已刪除的帳號）。'],
            ['key' => 'email', 'label' => 'Email', 'required' => true, 'example' => 'wang.xm@example.edu.tw', 'note' => '不可重複；「忘記密碼」會寄到這個信箱。'],
            ['key' => 'phone', 'label' => '電話', 'required' => false, 'example' => '0912-345-678', 'note' => '可不填。'],
            ['key' => 'role', 'label' => '身分', 'required' => true, 'example' => '教師', 'note' => '填身分名稱或代碼（見「身分一覽」工作表）。'],
            ['key' => 'department', 'label' => '部門', 'required' => false, 'example' => '資訊組', 'note' => '填啟用中的部門名稱（見「部門一覽」工作表），可不填。'],
            ['key' => 'password', 'label' => '初始密碼', 'required' => false, 'example' => '', 'note' => '至少 8 碼；不填的話，本人到登入頁按「忘記密碼」用 Email 領臨時密碼。不論有沒有填，第一次登入都要先設定新密碼。'],
            ['key' => 'is_active', 'label' => '啟用', 'required' => false, 'example' => '是', 'note' => '是／否；不填就是「是」。'],
        ];
    }

    public function referenceTables(): array
    {
        return [
            ['title' => '身分一覽', 'headings' => ['身分名稱', '身分代碼'], 'rows' => Role::orderBy('id')->get()->map(fn ($r) => [$r->name, $r->role_code])->all()],
            ['title' => '部門一覽', 'headings' => ['部門名稱'], 'rows' => Department::where('is_active', true)->orderBy('name')->pluck('name')->map(fn ($n) => [$n])->all()],
        ];
    }

    protected function checkRow(array $row, array &$seen): array
    {
        $errors = [];
        $data = [];

        $name = $row['name'] ?? '';
        if ($name === '') {
            $errors[] = '姓名必填';
        } elseif (mb_strlen($name) > 255) {
            $errors[] = '姓名太長（最多 255 字）';
        }
        $data['name'] = $name;

        // 帳號：格式、檔案內不重複、資料庫不重複（含軟刪除，因為資料表的唯一索引也算它們）。
        $account = $row['account'] ?? '';
        if ($account === '') {
            $errors[] = '帳號必填';
        } elseif (mb_strlen($account) < 3 || mb_strlen($account) > 30 || ! preg_match('/^[A-Za-z0-9._-]+$/', $account)) {
            $errors[] = '帳號要 3～30 字，只能用英文、數字與 . _ -';
        } elseif (isset($seen['accounts'][mb_strtolower($account)])) {
            $errors[] = "帳號「{$account}」在檔案裡重複（第 {$seen['accounts'][mb_strtolower($account)]} 列也用了）";
        } elseif (User::withTrashed()->whereRaw('LOWER(account) = ?', [mb_strtolower($account)])->exists()) {
            $errors[] = "帳號「{$account}」已經有人使用（可能是已刪除的帳號）";
        }
        $seen['accounts'][mb_strtolower($account)] ??= $seen['__line'] ?? '?';
        $data['account'] = $account;

        $email = $row['email'] ?? '';
        if ($email === '') {
            $errors[] = 'Email 必填';
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            $errors[] = "Email「{$email}」格式不對";
        } elseif (isset($seen['emails'][mb_strtolower($email)])) {
            $errors[] = "Email「{$email}」在檔案裡重複（第 {$seen['emails'][mb_strtolower($email)]} 列也用了）";
        } elseif (User::withTrashed()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->exists()) {
            $errors[] = "Email「{$email}」已經有人使用（可能是已刪除的帳號）";
        }
        $seen['emails'][mb_strtolower($email)] ??= $seen['__line'] ?? '?';
        $data['email'] = $email;

        $phone = $row['phone'] ?? '';
        if (mb_strlen($phone) > 30) {
            $errors[] = '電話太長（最多 30 字）';
        }
        $data['phone'] = $phone === '' ? null : $phone;

        // 身分：名稱或代碼。
        $roleText = $row['role'] ?? '';
        if ($roleText === '') {
            $errors[] = '身分必填';
        } else {
            $roleId = $this->roles()[mb_strtolower($roleText)] ?? null;
            $roleId ? $data['role_id'] = $roleId : $errors[] = "找不到身分「{$roleText}」";
        }

        // 部門：選填，填了就要是啟用中的部門。
        $departmentText = $row['department'] ?? '';
        if ($departmentText === '') {
            $data['department_id'] = null;
        } else {
            $departmentId = $this->departments()[mb_strtolower($departmentText)] ?? null;
            $departmentId ? $data['department_id'] = $departmentId : $errors[] = "找不到啟用中的部門「{$departmentText}」";
        }

        $password = $row['password'] ?? '';
        if ($password !== '' && mb_strlen($password) < 8) {
            $errors[] = '初始密碼至少 8 碼（或留空，讓本人用「忘記密碼」領臨時密碼）';
        }
        $data['password'] = $password;   // 留空的話，寫入時才產生隨機密碼

        $active = $this->toBool($row['is_active'] ?? '', true);
        $active === null ? $errors[] = '啟用請填「是」或「否」' : $data['is_active'] = $active;

        return [$errors, $data];
    }

    protected function persist(array $data): void
    {
        $user = new User(Arr::except($data, ['password']));
        // 沒填初始密碼：給一組沒有人知道的隨機密碼（本人用「忘記密碼」領臨時密碼再設定）。
        $user->password = $data['password'] !== '' ? $data['password'] : Str::random(40);
        $user->is_password_change_required = true;   // 第一次登入一定要先設定新密碼
        $user->save();

        // 操作紀錄不放密碼。
        AuditLogger::log('created', $user, Arr::except($data, ['password']), "批次匯入用戶「{$user->name}」");
    }

    /** @return array<string, int> 身分名稱與代碼（小寫）=> 編號 */
    private function roles(): array
    {
        if (! isset($this->cache['roles'])) {
            $map = [];
            foreach (Role::get() as $role) {
                $map[mb_strtolower($role->name)] = $role->id;
                $map[mb_strtolower($role->role_code)] = $role->id;
            }
            $this->cache['roles'] = $map;
        }

        return $this->cache['roles'];
    }

    /** @return array<string, int> */
    private function departments(): array
    {
        return $this->cache['departments'] ??= Department::where('is_active', true)->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])->all();
    }

    private array $cache = [];
}
