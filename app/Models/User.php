<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;   // 「註冊後要驗證 Email」功能，本系統帳號由管理員建立，所以沒有啟用
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;   // 用「屬性標註」宣告允許批次寫入的欄位
use Illuminate\Database\Eloquent\Attributes\Hidden;     // 用「屬性標註」宣告轉成 JSON 時要隱藏的欄位
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;           // 軟刪除：刪除帳號只是標記時間，資料與歷史紀錄保留，可還原
use Illuminate\Foundation\Auth\User as Authenticatable; // Laravel 內建的「可登入用戶」父類別（提供登入驗證所需功能）
use Illuminate\Notifications\Notifiable;                // 讓用戶可以收通知

// 允許批次寫入的欄位白名單（create / update 只會接受這些欄位）。
#[Fillable(['name', 'username', 'email', 'phone', 'password', 'role_id', 'department_id', 'is_active', 'last_login_at'])]
// 轉成 JSON / 陣列時一律隱藏密碼與「記住我」token，避免意外洩漏。
#[Hidden(['password', 'remember_token'])]
/**
 * 用戶（帳號，users 資料表）。每個用戶屬於一個身分（Role）與一個部門（Department），
 * 能使用哪些功能由身分的權限決定（hasPermission）。管理介面見 UserController。
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * 型別轉換：讓欄位在資料庫格式與 PHP 格式之間自動轉換。
     * - password => hashed：存入時自動加密，資料庫裡永遠不會有明碼
     * - is_active：0/1 ↔ false/true
     * - 兩個時間欄位轉成日期物件，才能用 ->format('Y-m-d H:i') 顯示
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** 這個用戶的身分。 */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /** 所屬部門（用戶主檔欄位）。 */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /** 是不是「系統管理員」角色，用戶主檔的防呆規則（例如不能刪掉最後一位管理員）會用到。 */
    public function isAdmin(): bool
    {
        // ?-> 是「沒有身分（null）時不要報錯，直接當成 null」。
        return $this->role?->slug === 'admin';
    }

    /** 這個用戶的身分有沒有開放某個權限（系統管理員永遠有）。Gate、@can、can: 中介層最後都是呼叫這裡。 */
    public function hasPermission(string $permission): bool
    {
        // 沒有身分的用戶一律沒有任何權限（?? false）。
        return $this->role?->hasPermission($permission) ?? false;
    }
}
