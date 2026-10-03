<?php

// 命名空間：這個類別所在的位置，要跟資料夾路徑對得上。
namespace App\Http\Controllers;

use App\Enums\RepairRequestStatus;   // 報修單狀態的列舉（新報修、已派工、處理中、待驗收、已結案）
use App\Models\Classroom;            // 教室資料表模型
use App\Models\Device;               // 設備資料表模型
use App\Models\RepairRequest;        // 報修單資料表模型
use App\Models\Role;                 // 身分資料表模型
use App\Models\User;                 // 用戶資料表模型
use Illuminate\Http\Request;         // 這一次瀏覽器送來的請求

/**
 * 主控台：用數字卡片與圖表呈現目前的概況，每一張卡片、每一塊圖表點下去都會跳到對應的功能區
 * （例如點「待驗收」跳到篩好狀態的報修看板、點設備狀態圖的某一塊跳到篩好狀態的設備主檔）。
 *
 * 跟權限的關係：報修的數字所有登入者都看得到；設備、用戶、教室的數字與圖表只有
 * 能進入對應主檔的人才會出現（不然點了也是 403，也不該讓沒權限的人看到統計數字）。
 *
 * 這支 Controller 只負責「算數字」，怎麼畫成卡片與圖表在 resources/views/dashboard.blade.php。
 * 【想新增一張卡片或圖表】在這裡算出數字放進 $data，再到 dashboard.blade.php 的 $cards / $charts 加一筆。
 */
class DashboardController extends Controller
{
    // __invoke：整個 Controller 只有「一個動作」時的寫法，路由直接寫 DashboardController::class 就會呼叫它。
    public function __invoke(Request $request)
    {
        $user = $request->user();   // 目前登入的人，後面用來判斷他有沒有權限看某些統計

        // ---- 報修單（所有人都有，但只統計自己看得到的）----
        // 一次查詢算出「每種狀態各有幾張」：select status, count(*) ... group by status。
        // pluck('total', 'status') 把結果變成 ['pending' => 3, 'assigned' => 1, ...] 方便用狀態取數字。
        $statusCounts = RepairRequest::query()
            ->visibleTo($user)   // 只統計這位用戶看得到的案件（能派工的人看全部，其他人只算自己的），數字才會和看板一致
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // 把每一種狀態整理成「圖表要用的資料」：代碼、中文名稱、數量、點擊後要跳去的網址。
        $repairStatuses = [];
        foreach (RepairRequestStatus::cases() as $status) {   // cases() = 列舉裡所有的狀態
            $repairStatuses[] = [
                'key' => $status->value,
                'label' => $status->label(),
                // ?? 0：這個狀態一張都沒有時，$statusCounts 裡沒有這個項目，就當成 0 張。
                'count' => (int) ($statusCounts[$status->value] ?? 0),
                // 點圖表這一塊時，跳到「已經篩好這個狀態」的報修看板。
                'url' => route('repairs.index', ['status' => $status->value]),
            ];
        }

        // 「進行中工單」= 除了「已結案」以外全部加起來。
        $openCount = collect($repairStatuses)->where('key', '!=', RepairRequestStatus::Completed->value)->sum('count');

        // 近 14 天每天新增幾張報修單（沒有的日子補 0，折線才不會斷）。
        $since = now()->subDays(13)->startOfDay();   // 13 天前的 00:00，加上今天剛好 14 天
        $perDay = RepairRequest::visibleTo($user)->where('created_at', '>=', $since)->get()
            // 依「建立日期」分組，再算每一組有幾張：['2026-10-03' => 7, ...]
            ->groupBy(fn (RepairRequest $request) => $request->created_at->format('Y-m-d'))
            ->map->count();
        $trend = [];
        for ($i = 0; $i < 14; $i++) {   // 一天一天走過這 14 天
            $date = $since->copy()->addDays($i);   // copy：避免改到原本的 $since
            $trend[] = ['label' => $date->format('m/d'), 'count' => (int) ($perDay[$date->format('Y-m-d')] ?? 0)];
        }

        // 先放「大家都看得到」的報修資料；需要權限的欄位先設成 null，下面有權限才填。
        // 畫面用「是不是 null」決定要不要顯示對應的卡片與圖表。
        $data = [
            'repairStatuses' => $repairStatuses,
            'openCount' => $openCount,
            'pendingCount' => (int) ($statusCounts[RepairRequestStatus::Pending->value] ?? 0),
            'pendingReviewCount' => (int) ($statusCounts[RepairRequestStatus::PendingReview->value] ?? 0),
            'trend' => $trend,
            'deviceStatuses' => null,
            'deviceTotal' => null,
            'roleDistribution' => null,
            'userTotal' => null,
            'classroomTotal' => null,
            'classroomAbnormal' => null,
        ];

        // ---- 設備（需要設備主檔權限）----
        if ($user->can('devices.manage')) {   // can()：這個人有沒有這項權限（管理員永遠是 true）
            $deviceCounts = Device::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
            // 每一種設備狀態整理成 {代碼、中文名、數量、點擊網址}；沒有的狀態補 0。
            $data['deviceStatuses'] = collect(Device::STATUSES)->map(fn (string $status) => [
                'key' => $status,
                'label' => __('dashboard.device_status.' . $status),   // 狀態的中文／英文名稱來自 lang/各語言資料夾/dashboard.php
                'count' => (int) ($deviceCounts[$status] ?? 0),
                'url' => route('devices.index', ['status' => $status]),
            ])->values()->all();
            $data['deviceTotal'] = Device::count();   // 設備總數
        }

        // ---- 用戶（需要用戶主檔權限）----
        if ($user->can('users.manage')) {
            // 每個身分各有幾位用戶。
            $roleCounts = User::query()->selectRaw('role_id, count(*) as total')->groupBy('role_id')->pluck('total', 'role_id');
            // 所有身分（連 0 人的也列出）都做成一根長條，點下去跳到「篩好該身分」的用戶主檔。
            $data['roleDistribution'] = Role::orderBy('id')->get()->map(fn (Role $role) => [
                'key' => $role->id,
                'label' => $role->name,
                'count' => (int) ($roleCounts[$role->id] ?? 0),
                'url' => route('users.index', ['role_id' => $role->id]),
            ])->all();
            $data['userTotal'] = User::count();   // 用戶總數
        }

        // ---- 教室（需要教室主檔權限）----
        if ($user->can('classrooms.manage')) {
            $data['classroomTotal'] = Classroom::count();
            // 預約狀態為 abnormal 的教室，卡片上會用紅字提示「設備異常教室 N 間」。
            $data['classroomAbnormal'] = Classroom::where('reservation_status', 'abnormal')->count();
        }

        // 把整理好的資料交給 resources/views/dashboard.blade.php 畫出來。
        return view('dashboard', $data);
    }
}
