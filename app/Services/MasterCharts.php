<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\RepairRequest;
use App\Models\Role;
use App\Models\User;

/**
 * 主檔頁面裡的統計圖表（原本放在主控台，現在收進對應的主檔，點頁首的「統計圖表」按鈕才看到）：
 * - 用戶主檔：各身分的用戶數
 * - 教室主檔：本月教室報修排行
 * - 設備主檔：設備各狀態的數量
 * - 設備類別主檔：本月設備類別報修排行
 *
 * 每個方法回傳同一種格式 {title, type, labels, counts, colors, urls, series}，
 * 畫面端（resources/views/charts/_master-chart.blade.php）照著畫、點了就跳 urls。
 */
class MasterCharts
{
    /** 長條圖用的一組輪替顏色。 */
    private const PALETTE = ['#0d6efd', '#6610f2', '#d63384', '#fd7e14', '#198754', '#20c997', '#0dcaf0', '#6c757d'];

    /** 各種設備狀態對應的圖表顏色。 */
    private const DEVICE_COLORS = ['normal' => '#198754', 'repairing' => '#ffc107', 'retired' => '#6c757d', 'disabled' => '#dc3545'];

    /** 用戶主檔：每個身分各有幾位用戶（長條圖），點哪一根就跳到篩好該身分的用戶主檔。 */
    public static function roleDistribution(): array
    {
        $counts = User::query()->selectRaw('role_id, count(*) as total')->groupBy('role_id')->pluck('total', 'role_id');
        $roles = Role::orderBy('id')->get();

        return [
            'title' => __('dashboard.charts.role_distribution'),
            'type' => 'bar',
            'labels' => $roles->pluck('name')->all(),
            'counts' => $roles->map(fn (Role $role) => (int) ($counts[$role->id] ?? 0))->all(),
            'colors' => $roles->keys()->map(fn ($i) => self::PALETTE[$i % count(self::PALETTE)])->all(),
            'urls' => $roles->map(fn (Role $role) => route('users.index', ['role_id' => $role->id]))->all(),
            'series' => __('dashboard.cards.users'),
        ];
    }

    /** 設備主檔：設備各狀態的數量（甜甜圈圖），點哪一塊就跳到篩好該狀態的設備主檔。 */
    public static function deviceStatus(): array
    {
        $counts = Device::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $rows = collect(Device::STATUSES);

        return [
            'title' => __('dashboard.charts.device_status'),
            'type' => 'doughnut',
            'labels' => $rows->map(fn (string $s) => __('dashboard.device_status.' . $s))->all(),
            'counts' => $rows->map(fn (string $s) => (int) ($counts[$s] ?? 0))->all(),
            'colors' => $rows->map(fn (string $s) => self::DEVICE_COLORS[$s] ?? '#6c757d')->all(),
            'urls' => $rows->map(fn (string $s) => route('devices.index', ['status' => $s]))->all(),
        ];
    }

    /** 教室主檔：本月教室報修排行（前 5 名，透過實際設備所在教室彙整）。 */
    public static function classroomRepairRanking(User $user): array
    {
        [$from, $to] = self::thisMonth();
        $rows = RepairRequest::query()
            ->visibleTo($user)
            ->join('devices', 'repair_requests.device_id', '=', 'devices.id')
            ->join('classrooms', 'devices.classroom_id', '=', 'classrooms.id')
            ->whereNull('devices.deleted_at')
            ->whereNull('classrooms.deleted_at')
            ->where('repair_requests.created_at', '>=', $from)
            ->where('repair_requests.created_at', '<', $to)
            ->select('classrooms.id', 'classrooms.classroom_code', 'classrooms.name')
            ->selectRaw('COUNT(repair_requests.id) AS total')
            ->groupBy('classrooms.id', 'classrooms.classroom_code', 'classrooms.name')
            ->orderByDesc('total')
            ->orderBy('classrooms.classroom_code')
            ->limit(5)
            ->get();

        return self::ranking(__('dashboard.charts.classroom_repair_ranking'), '#dc3545',
            $rows->map(fn ($r) => trim($r->classroom_code . ' ' . $r->name))->all(),
            $rows->map(fn ($r) => (int) $r->total)->all());
    }

    /** 設備類別主檔：本月設備類別報修排行（前 5 名，只統計可見、已綁定實際設備的報修單）。 */
    public static function deviceCategoryRepairRanking(User $user): array
    {
        [$from, $to] = self::thisMonth();
        $rows = RepairRequest::query()
            ->visibleTo($user)
            ->join('devices', 'repair_requests.device_id', '=', 'devices.id')
            ->join('device_categories', 'devices.device_category_id', '=', 'device_categories.id')
            ->whereNull('devices.deleted_at')
            ->where('repair_requests.created_at', '>=', $from)
            ->where('repair_requests.created_at', '<', $to)
            ->select('device_categories.id', 'device_categories.name')
            ->selectRaw('COUNT(repair_requests.id) AS total')
            ->groupBy('device_categories.id', 'device_categories.name')
            ->orderByDesc('total')
            ->orderBy('device_categories.name')
            ->limit(5)
            ->get();

        return self::ranking(__('dashboard.charts.device_category_repair_ranking'), '#0d6efd',
            $rows->map(fn ($r) => $r->name)->all(),
            $rows->map(fn ($r) => (int) $r->total)->all());
    }

    /** 排行圖共用格式：長條圖，點擊後前往報修看板。 */
    private static function ranking(string $title, string $color, array $labels, array $counts): array
    {
        return [
            'title' => $title,
            'type' => 'bar',
            'labels' => $labels,
            'counts' => $counts,
            'colors' => array_fill(0, count($labels), $color),
            'urls' => array_fill(0, count($labels), route('repairs.index')),
            'series' => __('dashboard.charts.repair_trend_series'),
        ];
    }

    /** @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon} 本月第一天與下個月第一天 */
    private static function thisMonth(): array
    {
        $start = now()->startOfMonth();

        return [$start, $start->copy()->addMonth()];
    }
}
