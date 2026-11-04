<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * 洋蔥式選單的內容（手機抽屜、桌面側邊欄、主控台的「功能入口」都用這一份資料）。
 *
 * 新增選單項目：加在下面 build() 的 $menu 陣列，權限規則寫在項目最後面的 $can(...)，三個地方同時生效。
 * 結構：第一層是「單一連結（link）」或「分類（group）」；點分類往內一層看到裡面的連結。
 */
class NavMenu
{
    /**
     * @return array{
     *     menu: array<int, array<string, mixed>>,
     *     groups: array<int, array<string, mixed>>,
     *     openGroup: string|null
     * }
     *   menu = 第一層（已依權限整理）；groups = 第一層裡的分類；openGroup = 目前頁面所在的分類 id（沒有就是 null）。
     */
    public static function build(?User $user): array
    {
        // 有沒有某個權限（沒登入或沒有身分一律 false）。
        $can = fn (string $permission) => $user && $user->can($permission);
        // 路由存在才產生網址，其他組員的模組還沒合併時不會出現壞連結。
        $link = fn (string $label, string $icon, string $route, array $active, bool $show = true) => [
            'label' => $label,
            'icon' => $icon,
            'href' => Route::has($route) ? route($route) : '#',
            'active' => request()->routeIs(...$active),
            'show' => $show && Route::has($route),
        ];

        $menu = [
            ['type' => 'link'] + $link(__('common.nav.dashboard'), 'bi-speedometer2', 'dashboard', ['dashboard']),
            ['type' => 'group', 'id' => 'work', 'label' => __('common.nav.repair_section'), 'icon' => 'bi-tools', 'children' => [
                $link(__('common.nav.repair_requests'), 'bi-tools', 'repairs.index', ['repairs.*', 'repair-logs.*']),
                $link(__('common.nav.knowledge_base'), 'bi-book', 'knowledge-base.index', ['knowledge-base.*']),
            ]],
            ['type' => 'group', 'id' => 'maintenance', 'label' => __('common.nav.maintenance_section'), 'icon' => 'bi-wrench-adjustable', 'children' => [
                $link(__('common.nav.maintenance_items'), 'bi-list-check', 'maintenance-items.index', ['maintenance-items.*'], $can('maintenance.view')),
                $link(__('common.nav.maintenance_plans'), 'bi-calendar-event', 'maintenance-plans.index', ['maintenance-plans.*'], $can('maintenance.view')),
                $link(__('common.nav.maintenance_orders'), 'bi-wrench-adjustable', 'maintenance-orders.index', ['maintenance-orders.*'], $can('maintenance.view')),
                $link(__('common.nav.device_profile'), 'bi-journal-text', 'device-profile.index', ['device-profile.*'], $can('maintenance.view')),
                $link(__('common.nav.preventive_ai'), 'bi-robot', 'preventive-candidates.index', ['preventive-candidates.*', 'ai-settings.*'], $can('ai-maintenance.manage')),
            ]],
            ['type' => 'group', 'id' => 'space', 'label' => __('common.nav.resource_section'), 'icon' => 'bi-building', 'children' => [
                $link(__('common.nav.reservations'), 'bi-calendar-check', 'reservations.index', ['reservations.*']),
                // 零件庫存需要 parts.manage（身分主檔勾選；設備管理員預設有）。
                $link(__('common.nav.parts'), 'bi-box-seam', 'parts.index', ['parts.*'], $can('parts.manage')),
            ]],
            ['type' => 'group', 'id' => 'master', 'label' => __('common.nav.master_section'), 'icon' => 'bi-database', 'children' => [
                $link(__('common.nav.users'), 'bi-people', 'users.index', ['users.*'], $can('users.manage')),
                $link(__('common.nav.roles'), 'bi-shield-lock', 'roles.index', ['roles.*'], $can('roles.manage')),
                $link(__('common.nav.departments'), 'bi-diagram-3', 'departments.index', ['departments.*'], $can('departments.manage')),
                $link(__('common.nav.classrooms'), 'bi-building', 'classrooms.index', ['classrooms.*'], $can('classrooms.manage')),
                $link(__('common.nav.device_categories'), 'bi-tags', 'device-categories.index', ['device-categories.*'], $can('device-categories.manage')),
                $link(__('common.nav.devices'), 'bi-pc-display', 'devices.index', ['devices.*'], $can('devices.manage')),
            ]],
            ['type' => 'group', 'id' => 'system', 'label' => __('common.nav.system_section'), 'icon' => 'bi-gear', 'children' => [
                $link(__('common.nav.audit_logs'), 'bi-clock-history', 'audit-logs.index', ['audit-logs.*'], $can('audit-logs.view')),
            ]],
        ];

        // 整理：
        //  1. 把沒權限的子項目拿掉；
        //  2. 一個子項目都沒有的分類整個不顯示；
        //  3. 只剩一個子項目的分類，直接變成第一層的單一連結（少點一層）；
        //  4. 分類裡有「目前所在頁面」就標記 active，並記下它的 id（預設展開那一層，讓使用者知道自己在哪）。
        $openGroup = null;
        foreach ($menu as $i => $entry) {
            if ($entry['type'] !== 'group') {
                continue;
            }
            $children = array_values(array_filter($entry['children'], fn ($c) => $c['show']));
            if (count($children) === 1) {
                $menu[$i] = ['type' => 'link'] + $children[0];

                continue;
            }
            $entry['children'] = $children;
            $entry['show'] = count($children) > 0;
            $entry['active'] = count(array_filter($children, fn ($c) => $c['active'])) > 0;
            if ($entry['show'] && $entry['active']) {
                $openGroup = $entry['id'];
            }
            $menu[$i] = $entry;
        }

        $groups = array_values(array_filter($menu, fn ($e) => $e['type'] === 'group' && $e['show']));

        return ['menu' => $menu, 'groups' => $groups, 'openGroup' => $openGroup];
    }
}
