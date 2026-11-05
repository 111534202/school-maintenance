<?php

namespace App\Support;

use Illuminate\Routing\Route;

/**
 * 頂端列「返回」按鈕的去處（沒有瀏覽紀錄的上一頁時才會用到，例如直接開網址）。
 *
 * 規則：
 *   1. 詳細／新增／編輯頁（xxx.show、xxx.create、xxx.edit）→ 該功能的清單頁（xxx.index）。
 *   2. 規則 1 推算不到的少數頁面，列在下面的 EXPLICIT（例如保養結果頁回它所屬的工單）。
 *   3. 其他（清單頁、找不到父頁的頁面）→ 主控台。
 * 全站的返回都放在頂端列，各頁面不再自己放「返回列表」；新增頁面時若推算不到父頁，在 EXPLICIT 補一行即可。
 */
class BackLink
{
    /** 路由名稱 => [父頁路由名稱, 要帶給父頁的路由參數名稱（依序）] */
    private const EXPLICIT = [
        'ai-settings.edit' => ['preventive-candidates.index', []],
        'maintenance-orders.results.create' => ['maintenance-orders.show', ['maintenanceOrder']],
        'maintenance-orders.results.show' => ['maintenance-orders.show', ['maintenanceOrder']],
        'parts.purchases.create' => ['parts.show', ['part']],
        'parts.purchases.edit' => ['parts.show', ['part']],
        'repair-logs.create' => ['repairs.show', ['repair_request']],
    ];

    public static function url(?Route $route): string
    {
        $name = $route?->getName();

        if ($name === null || $name === 'dashboard') {
            return route('dashboard');
        }

        if (isset(self::EXPLICIT[$name])) {
            [$parent, $parameters] = self::EXPLICIT[$name];

            return route($parent, array_map(fn (string $parameter) => $route->parameter($parameter), $parameters));
        }

        if (preg_match('/\.(create|edit|show)$/', $name)) {
            $parent = preg_replace('/\.(create|edit|show)$/', '.index', $name);
            $parentRoute = app('router')->getRoutes()->getByName($parent);

            // 只接受不需要路由參數的清單頁；需要參數又沒登記在 EXPLICIT 的，退回主控台。
            if ($parentRoute !== null && $parentRoute->parameterNames() === []) {
                return route($parent);
            }
        }

        return route('dashboard');
    }
}
