<?php

/*
 * 保養工單設定。
 *
 * manual_orders：保養計畫列表要不要有「建立工單」按鈕（手動馬上產生這一期的工單）。
 *   預設關閉：工單只由系統自動產生——排程每天檢查，到期日前 3 天產生；新增／修改計畫時下次到期日已在 3 天內也會馬上產生；
 *   工單完成或取消後，下一期進入 3 天內也會接著產生（規則見 App\Services\DueMaintenanceOrderGenerator）。
 *   要重新打開手動建立，在 .env 加 MAINTENANCE_MANUAL_ORDERS=true。
 */
return [
    'manual_orders' => (bool) env('MAINTENANCE_MANUAL_ORDERS', false),
];
