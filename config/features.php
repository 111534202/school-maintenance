<?php

// 功能開關：先做好、但暫時不想在畫面上顯示的功能。只影響選單顯示，不刪資料、不改權限。
return [
    // AI 預防保養（選單：保養管理 → AI 預防保養）。預設隱藏；要恢復就在 .env 加上 FEATURE_AI_MAINTENANCE=true。
    'ai_maintenance' => (bool) env('FEATURE_AI_MAINTENANCE', false),
];
