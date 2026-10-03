<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// 為五個內建身分各建立一個測試帳號（密碼都是 password）。
// 登入帳號名稱：admin（系統管理員）、it_manager（資訊組主管）、repairer（維修人員）、teacher（教師）、executive（主管）。
// 更多示範用戶（不同部門、已停用、已刪除）在 UserDemoSeeder。
class UserSeeder extends Seeder
{
    /**
     * 每個角色建立一個測試帳號，密碼統一為 password，供五種角色登入測試。
     * 登入用 username（帳號名稱）；email 目前是佔位用的測試信箱，之後在使用者主檔
     * 改成真實信箱（例如 Gmail），派工通知信會寄到那個信箱。
     */
    public function run(): void
    {
        // 測試帳號清單：slug 對應的身分、username 登入帳號、name 姓名、email 信箱。
        $accounts = [
            ['slug' => 'admin', 'username' => 'admin', 'name' => '管理員', 'email' => 'admin@school.test'],
            ['slug' => 'it_manager', 'username' => 'it_manager', 'name' => '資訊組主管', 'email' => 'it_manager@school.test'],
            ['slug' => 'technician', 'username' => 'repairer', 'name' => '維修人員', 'email' => 'repairer@school.test'],
            ['slug' => 'teacher', 'username' => 'teacher', 'name' => '教師／教室管理人', 'email' => 'teacher@school.test'],
            ['slug' => 'executive', 'username' => 'executive', 'name' => '主管／行政人員', 'email' => 'executive@school.test'],
        ];

        // 逐一建立或更新。
        foreach ($accounts as $account) {
            // 找出這個帳號要用的身分（RoleSeeder 已先建立）。
            $role = Role::where('slug', $account['slug'])->first();

            // 先用 username 找，找不到再用 email 找（舊資料只有 email 沒有 username），
            // 避免重複執行 seeder 或舊資料庫補跑時撞到 email 的唯一限制。
            // 先找看看這個帳號是否已存在（避免重複建立）。
            $user = User::where('username', $account['username'])
                ->orWhere('email', $account['email'])
                ->first() ?? new User();

            // 把資料放進用戶物件（fill 只會接受 User 模型 $fillable 允許的欄位）。
            $user->fill([
                'name' => $account['name'],
                'username' => $account['username'],
                'email' => $account['email'],
                'role_id' => $role?->id,
                // 密碼設成 password；User 模型會在存入時自動加密。
                'password' => 'password',
            ]);
            // 標記 Email 已驗證（本系統不走 Email 驗證流程）。
            $user->email_verified_at = now();
            // 存進資料庫（新用戶會新增，既有用戶會更新）。
            $user->save();
        }
    }
}
