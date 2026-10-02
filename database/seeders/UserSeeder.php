<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * 每個角色建立一個測試帳號，密碼統一為 password，供五種角色登入測試。
     *
     * 工程實作修正：User Model 的 password 欄位已經有 'hashed' cast
     * （assign 時會自動呼叫 Hash::make），這裡如果再手動 Hash::make('password')
     * 一次，就會被雙重雜湊，導致密碼永遠核對不過、無法登入。
     * 修正方式：直接指定明文密碼字串，交給 Model 的 cast 處理雜湊。
     */
    public function run(): void
    {
        $accounts = [
            ['slug' => 'admin', 'name' => '系統管理員測試帳號', 'email' => 'admin@school.test'],
            ['slug' => 'it_manager', 'name' => '資訊組主管測試帳號', 'email' => 'it_manager@school.test'],
            ['slug' => 'technician', 'name' => '維修人員測試帳號', 'email' => 'technician@school.test'],
            ['slug' => 'teacher', 'name' => '教師教室管理人測試帳號', 'email' => 'teacher@school.test'],
            ['slug' => 'executive', 'name' => '主管行政人員測試帳號', 'email' => 'executive@school.test'],
        ];

        foreach ($accounts as $account) {
            $role = Role::where('slug', $account['slug'])->first();

            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'role_id' => $role?->id,
                    'password' => 'password',
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
