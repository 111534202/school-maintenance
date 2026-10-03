<?php

// 這個檔案所在的資料夾（命名空間）。Laravel 靠它找到這個類別，路徑要跟資料夾對得上，不要亂改。
namespace App\Http\Controllers;

// 以下 use 是「把要用到的類別先宣告進來」，後面程式就可以直接寫短名字（例如 User），
// 不用每次都寫完整路徑（App\Models\User）。
use App\Models\Department;                         // 部門主檔的資料表模型
use App\Models\Role;                               // 身分主檔的資料表模型
use App\Models\User;                               // 用戶（帳號）資料表模型
use App\Services\AuditLogger;                      // 共用的「操作紀錄」寫入工具
use Illuminate\Http\RedirectResponse;              // 「導向到別的網址」這種回應的型別
use Illuminate\Http\Request;                       // 代表瀏覽器送來的這一次請求（表單內容、網址參數都在裡面）
use Illuminate\Support\Arr;                        // 陣列小工具（這裡用來「去掉某個欄位」）
use Illuminate\Support\Facades\Auth;               // 取得「目前登入的人」
use Illuminate\Support\Facades\DB;                 // 直接操作資料庫
use Illuminate\Validation\Rule;                    // 進階驗證規則（例如「不可重複，但排除自己」）
use Illuminate\Validation\Rules\Password;          // 密碼強度規則

/**
 * 用戶主檔（帳號管理）：列表／篩選、新增、編輯、啟用／停用、密碼重設、刪除（軟刪除）與還原。
 * 只有擁有「users.manage」權限的身分才進得來（見 routes/web.php 的 can:users.manage）。
 *
 * 【Controller 是什麼？】
 * 瀏覽器開網址 → routes/web.php 決定交給哪個 Controller 的哪個方法 → 方法處理資料 →
 * 回傳畫面（view）或導向別頁（redirect）。這個類別裡每個 public 方法就對應一個網址動作。
 *
 * 一般企業帳號管理會有的防呆規則都集中在這支 Controller：
 * - 不能刪除、停用自己，也不能把自己的角色降級（避免把自己鎖在系統外面）。
 * - 系統一定要留著至少一位「啟用中的系統管理員」，最後一位不能被刪除、停用或降級。
 * - 停用、刪除、重設密碼後，該帳號目前已登入的連線（sessions）會被立刻踢掉。
 * - 刪除是軟刪除（資料還在，只是標記已刪除），帳號與歷史紀錄（報修單、操作紀錄）保留，可以還原。
 * 所有異動都寫進共用的 audit_logs（AuditLogger），密碼不會寫進紀錄。
 *
 * 【想新增一個用戶欄位（例如「分機」）要改哪裡？】
 * 1. 新增一支 migration 在 users 資料表加欄位；
 * 2. app/Models/User.php 的 $fillable 加上欄位名稱（否則存不進去）；
 * 3. 本檔 rules() 加驗證規則；
 * 4. resources/views/users/_form.blade.php 加輸入框、index.blade.php 加顯示欄；
 * 5. lang/zh_TW/users.php 與 lang/en/users.php 加欄位的中英文名稱。
 */
class UserController extends Controller
{
    /**
     * 用戶列表（網址：GET /users），支援篩選與分頁。
     * 可用的網址參數：status（active 啟用／inactive 停用／deleted 已刪除）、keyword、role_id、department_id。
     * 主控台的圖表點擊也是帶這些參數過來（例如 /users?role_id=3）。
     */
    public function index(Request $request)
    {
        // 取出網址上的 status 參數並轉成字串；沒帶的話是空字串。
        $status = $request->string('status')->toString();

        // 組一條查詢：query() 開始一條空查詢，後面一段一段加條件，最後 paginate 才真的去資料庫撈。
        $users = User::query()
            // with：順便把「身分」與「部門」一起查好，列表顯示名稱時就不會每一列都再多查一次資料庫（避免 N+1 慢查詢）。
            ->with(['role', 'department'])
            // when(條件, 動作)：條件成立才套用這段篩選，沒選的篩選就直接略過。
            // onlyTrashed()：只看「已被軟刪除」的帳號（平常的查詢會自動排除它們）。
            ->when($status === 'deleted', fn ($query) => $query->onlyTrashed())
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))     // 只看啟用中
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))  // 只看已停用
            // 關鍵字：filled() 表示有填且不是空白。同時比對帳號、姓名、Email、電話，任一個符合就算。
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = $request->string('keyword')->toString();
                // 這裡再包一層 where(function...) 是為了讓「or」只在括號內生效，
                // 不會把前面的其他篩選條件（例如停用狀態）一起「或」掉。
                $query->where(function ($q) use ($keyword) {
                    $q->where('username', 'like', "%{$keyword}%")    // like %字% = 「包含這段字」
                        ->orWhere('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->orWhere('phone', 'like', "%{$keyword}%");
                });
            })
            // 依身分、部門篩選（下拉選單送來的是編號，所以用 integer 轉成整數）。
            ->when($request->filled('role_id'), fn ($query) => $query->where('role_id', $request->integer('role_id')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->orderBy('id')       // 依編號由小到大排序
            ->paginate(15)        // 每頁 15 筆；想改每頁筆數就改這個數字
            ->withQueryString();  // 換頁時保留原本的篩選參數，不然翻到第 2 頁篩選就不見了

        // 把資料交給 resources/views/users/index.blade.php 畫成網頁。
        return view('users.index', [
            'users' => $users,
            // 篩選列的下拉選單選項：全部身分、全部部門（這裡篩選要看得到停用的部門，所以不過濾）。
            'roles' => Role::orderBy('id')->get(),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    /** 顯示「新增用戶」表單（GET /users/create）。下拉選單的資料由 formData() 提供。 */
    public function create()
    {
        return view('users.create', $this->formData());
    }

    /** 接收新增表單（POST /users），驗證通過就建立帳號。 */
    public function store(Request $request)
    {
        // validate：依規則檢查表單。不通過的話 Laravel 會自動導回表單並顯示錯誤訊息，下面的程式不會執行。
        // 「+」是把兩個陣列合併：共用規則 + 新增專用的密碼規則（至少 8 碼、要輸入兩次確認一致）。
        $data = $request->validate($this->rules() + [
            'password' => ['required', Password::min(8), 'confirmed'],
        ]);

        // 建立帳號。密碼在 User 模型裡設定成「存入前自動加密（hash）」，所以資料庫裡不會有明碼。
        $user = User::create($data);

        // 寫操作紀錄；Arr::except 把 password 欄位拿掉，確保密碼不會被記進紀錄。
        AuditLogger::log('created', $user, Arr::except($data, ['password']));

        // 導回列表，並帶一則成功訊息（with 的內容只會顯示一次，畫面上由共用版面顯示）。
        return redirect()->route('users.index')->with('success', __('users.flash.created'));
    }

    /** 顯示「編輯用戶」表單（GET /users/{user}/edit）。User $user 由 Laravel 依網址上的編號自動找出來。 */
    public function edit(User $user)
    {
        // 把目前的部門編號傳給 formData：即使該部門已被停用，下拉選單仍要保留它，編輯才不會被迫換部門。
        return view('users.edit', $this->formData($user->department_id) + ['user' => $user]);
    }

    /** 接收編輯表單（PUT /users/{user}），這裡有最多的防呆規則。 */
    public function update(Request $request, User $user)
    {
        $data = $request->validate($this->rules($user));

        // 算出「儲存之後」這個人會是什麼狀態，後面判斷要不要擋下來。
        $newRole = Role::find($data['role_id']);
        $willBeAdmin = $newRole?->slug === 'admin';   // ?-> 表示找不到身分時不要報錯，直接當成 null
        $willBeActive = (bool) $data['is_active'];

        // 不能把自己降級或停用自己，不然會把自己鎖在系統外面。
        // Auth::id() 是目前登入者的編號；要改的正好是自己，而且身分有變或被設為停用，就擋下。
        if ($user->id === Auth::id() && ($data['role_id'] != $user->role_id || ! $willBeActive)) {
            // back()：回到上一頁；withInput() 保留剛剛填的內容；with('error') 帶錯誤訊息。
            return back()->withInput()->with('error', __('users.errors.self_protected'));
        }

        // 最後一位啟用中的管理員不能被降級或停用，否則系統就沒人能管理了。
        if ($this->isLastActiveAdmin($user) && (! $willBeAdmin || ! $willBeActive)) {
            return back()->withInput()->with('error', __('users.errors.last_admin'));
        }

        $wasActive = $user->is_active;   // 先記住「改之前」是不是啟用，等下判斷是不是剛被停用
        $user->update($data);            // 把表單資料寫進資料庫

        AuditLogger::log('updated', $user, $data);

        // 從「啟用」變成「停用」：把他目前登入中的連線立刻踢掉。
        if ($wasActive && ! $user->is_active) {
            $this->revokeSessions($user);
        }

        return redirect()->route('users.index')->with('success', __('users.flash.updated'));
    }

    /** 啟用／停用切換（列表上的快速按鈕）。 */
    public function toggle(User $user): RedirectResponse
    {
        // 不能停用自己。
        if ($user->id === Auth::id()) {
            return back()->with('error', __('users.errors.self_protected'));
        }

        // 要停用的是最後一位啟用中的管理員 → 擋下。
        if ($user->is_active && $this->isLastActiveAdmin($user)) {
            return back()->with('error', __('users.errors.last_admin'));
        }

        // 把 is_active 反過來：true 變 false、false 變 true。
        $user->update(['is_active' => ! $user->is_active]);

        AuditLogger::log('status_changed', $user, ['is_active' => $user->is_active]);

        // 剛被停用就踢掉他的連線。
        if (! $user->is_active) {
            $this->revokeSessions($user);
        }

        // 依結果顯示「已啟用」或「已停用」訊息。
        return back()->with('success', $user->is_active ? __('users.flash.activated') : __('users.flash.deactivated'));
    }

    /** 管理員直接幫使用者設定新密碼（使用者忘記密碼時用）。 */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        // 只驗證密碼一個欄位：至少 8 碼，且「確認密碼」欄位要一致。
        $data = $request->validate([
            'password' => ['required', Password::min(8), 'confirmed'],
        ]);

        // 存入新密碼（User 模型會自動加密）。
        $user->update(['password' => $data['password']]);

        // 密碼本身絕對不寫進操作紀錄，只記「重設過密碼」這件事。
        AuditLogger::log('password_reset', $user);
        // 舊密碼登入的連線全部失效，對方必須用新密碼重新登入。
        $this->revokeSessions($user);

        return back()->with('success', __('users.flash.password_reset'));
    }

    /** 刪除帳號（DELETE /users/{user}）。這是「軟刪除」：只是標記 deleted_at，資料還在，之後可以還原。 */
    public function destroy(User $user): RedirectResponse
    {
        // 不能刪自己。
        if ($user->id === Auth::id()) {
            return back()->with('error', __('users.errors.self_protected'));
        }

        // 不能刪最後一位啟用中的管理員。
        if ($this->isLastActiveAdmin($user)) {
            return back()->with('error', __('users.errors.last_admin'));
        }

        $user->delete();   // 因為 User 模型有用 SoftDeletes，這裡不是真的從資料庫刪掉

        AuditLogger::log('deleted', $user);
        $this->revokeSessions($user);   // 被刪的人立刻被踢下線

        return redirect()->route('users.index')->with('success', __('users.flash.deleted'));
    }

    /** 還原被刪除（軟刪除）的帳號。路由有加 withTrashed()，才找得到已刪除的帳號。 */
    public function restore(User $user): RedirectResponse
    {
        $user->restore();   // 清掉 deleted_at，帳號回到列表

        AuditLogger::log('restored', $user);

        return redirect()->route('users.index')->with('success', __('users.flash.restored'));
    }

    /**
     * 新增／編輯共用的驗證規則。
     * 編輯時傳入 $user，唯一性檢查會「排除自己」，這樣不改帳號直接存檔才不會被說「帳號已被使用」。
     * 想放寬或加嚴某欄位，改這裡就會同時套用到新增與編輯。
     */
    private function rules(?User $user = null): array
    {
        return [
            // 帳號：必填、3~30 字、只能英數與 . _ -（regex 是這個字元範圍的規則）、不可與別人重複。
            'username' => [
                'required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($user?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            // Email：必填、格式要正確、不可與別人重複。
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            // 電話：可以不填（nullable）。
            'phone' => ['nullable', 'string', 'max:30'],
            // 身分、部門必須是資料庫裡真的存在的編號，防止有人偽造表單亂傳。
            'role_id' => ['required', 'exists:roles,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** 身分與部門下拉選單的來源：身分主檔、部門主檔（部門只列啟用中的，已選的那個即使被停用也保留）。 */
    private function formData(?int $currentDepartmentId = null): array
    {
        return [
            'roles' => Role::orderBy('id')->get(),
            // forSelect 定義在 Department 模型，負責「只列啟用中的部門 + 保留目前已選的那個」。
            'departments' => Department::forSelect($currentDepartmentId)->get(),
        ];
    }

    /** 這個帳號是不是系統裡「最後一位啟用中的系統管理員」。 */
    private function isLastActiveAdmin(User $user): bool
    {
        // 他本來就不是啟用中的管理員（或已被刪除），當然不可能是「最後一位」。
        if (! $user->isAdmin() || ! $user->is_active || $user->trashed()) {
            return false;
        }

        // 反過來查：除了他以外，還有沒有其他「啟用中的管理員」？一個都沒有，他就是最後一位。
        return ! User::query()
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('slug', 'admin'))   // 身分代碼是 admin
            ->exists();
    }

    /** 把這個帳號目前所有已登入的連線踢掉（sessions 用資料庫儲存時才有這張表可清）。 */
    private function revokeSessions(User $user): void
    {
        // 登入狀態如果存在檔案或快取（driver 不是 database），就沒有資料表可以刪，這裡直接略過。
        if (config('session.driver') === 'database') {
            // 刪掉 sessions 表中屬於這個人的紀錄，他下一個動作就會被要求重新登入。
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
