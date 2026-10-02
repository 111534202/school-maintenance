<?php

namespace App\Http\Controllers;

use App\Enums\RepairRequestStatus;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\RepairRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * 主控台：用數字卡片與圖表呈現目前的概況，每一張卡片、每一塊圖表點下去都會跳到對應的功能區
 * （例如點「待驗收」跳到篩好狀態的報修看板、點設備狀態圖的某一塊跳到篩好狀態的設備主檔）。
 *
 * 跟權限的關係：報修的數字所有登入者都看得到；設備、用戶、教室的數字與圖表只有
 * 能進入對應主檔的人才會出現（不然點了也是 403，也不該讓沒權限的人看到統計數字）。
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        // ---- 報修單（所有人）----
        $statusCounts = RepairRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $repairStatuses = [];
        foreach (RepairRequestStatus::cases() as $status) {
            $repairStatuses[] = [
                'key' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($statusCounts[$status->value] ?? 0),
                'url' => route('repairs.index', ['status' => $status->value]),
            ];
        }

        $openCount = collect($repairStatuses)->where('key', '!=', RepairRequestStatus::Completed->value)->sum('count');

        // 近 14 天每天新增幾張報修單（沒有的日子補 0，折線才不會斷）。
        $since = now()->subDays(13)->startOfDay();
        $perDay = RepairRequest::where('created_at', '>=', $since)->get()
            ->groupBy(fn (RepairRequest $request) => $request->created_at->format('Y-m-d'))
            ->map->count();
        $trend = [];
        for ($i = 0; $i < 14; $i++) {
            $date = $since->copy()->addDays($i);
            $trend[] = ['label' => $date->format('m/d'), 'count' => (int) ($perDay[$date->format('Y-m-d')] ?? 0)];
        }

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
        if ($user->can('devices.manage')) {
            $deviceCounts = Device::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
            $data['deviceStatuses'] = collect(Device::STATUSES)->map(fn (string $status) => [
                'key' => $status,
                'label' => __('dashboard.device_status.' . $status),
                'count' => (int) ($deviceCounts[$status] ?? 0),
                'url' => route('devices.index', ['status' => $status]),
            ])->values()->all();
            $data['deviceTotal'] = Device::count();
        }

        // ---- 用戶（需要用戶主檔權限）----
        if ($user->can('users.manage')) {
            $roleCounts = User::query()->selectRaw('role_id, count(*) as total')->groupBy('role_id')->pluck('total', 'role_id');
            $data['roleDistribution'] = Role::orderBy('id')->get()->map(fn (Role $role) => [
                'key' => $role->id,
                'label' => $role->name,
                'count' => (int) ($roleCounts[$role->id] ?? 0),
                'url' => route('users.index', ['role_id' => $role->id]),
            ])->all();
            $data['userTotal'] = User::count();
        }

        // ---- 教室（需要教室主檔權限）----
        if ($user->can('classrooms.manage')) {
            $data['classroomTotal'] = Classroom::count();
            $data['classroomAbnormal'] = Classroom::where('reservation_status', 'abnormal')->count();
        }

        return view('dashboard', $data);
    }
}
