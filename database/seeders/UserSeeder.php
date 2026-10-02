<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * 每個角色建立一個測試帳號，密碼統一為 password，供五種角色登入測試。
     * 登入用 username（帳號名稱）；email 目前是佔位用的測試信箱，之後在使用者主檔
     * 改成真實信箱（例如 Gmail），派工通知信會寄到那個信箱。
     */
    public function run(): void
    {
        $accounts = [
            ['slug' => 'admin', 'username' => 'admin', 'name' => '管理員', 'email' => 'admin@school.test'],
            ['slug' => 'it_manager', 'username' => 'it_manager', 'name' => '資訊組主管', 'email' => 'it_manager@school.test'],
            ['slug' => 'technician', 'username' => 'repairer', 'name' => '維修人員', 'email' => 'repairer@school.test'],
            ['slug' => 'teacher', 'username' => 'teacher', 'name' => '教師／教室管理人', 'email' => 'teacher@school.test'],
            ['slug' => 'executive', 'username' => 'executive', 'name' => '主管／行政人員', 'email' => 'executive@school.test'],
        ];

        foreach ($accounts as $account) {
            $role = Role::where('slug', $account['slug'])->first();

            // 先用 username 找，找不到再用 email 找（舊資料只有 email 沒有 username），
            // 避免重複執行 seeder 或舊資料庫補跑時撞到 email 的唯一限制。
            $user = User::where('username', $account['username'])
                ->orWhere('email', $account['email'])
                ->first() ?? new User();

            $user->fill([
                'name' => $account['name'],
                'username' => $account['username'],
                'email' => $account['email'],
                'role_id' => $role?->id,
                'password' => 'password',
            ]);
            $user->email_verified_at = now();
            $user->save();
        }
    }
}
