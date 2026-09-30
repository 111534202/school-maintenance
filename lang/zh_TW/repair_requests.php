<?php

// i18n：報修單模組（看板、新增表單、詳細頁：派工/開始處理/驗收/退回/重新指派）。
// 修改這個檔案時，記得同步修改 lang/en/repair_requests.php，兩邊的 key 必須完全一致。
return [
    'board_title' => '維修案件看板',
    'add_request' => '＋ 新增報修',
    'empty_list' => '目前沒有符合條件的報修案件。',

    'filter' => [
        'status' => '狀態',
        'status_all' => '全部',
        'location' => '教室／地點',
        'location_placeholder' => '例如：A101',
        'assignee' => '維修人員',
        'assignee_placeholder' => '例如：王小明',
    ],

    'table' => [
        'title' => '標題',
        'device_location' => '設備／地點',
        'impact_level' => '影響程度',
        'affects_class' => '影響上課',
        'status' => '狀態',
        'assignee' => '維修人員',
        'submitted_at' => '送出時間',
        'waiting_time' => '等待多久',
    ],

    'unassigned' => '未指派',
    'active_case_suffix' => '（未結案 :count 件）',
    'not_filled' => '未填寫',
    'yes' => '是',
    'no' => '否',

    'impact_level' => [
        'low' => '輕微',
        'medium' => '中等',
        'high' => '嚴重',
    ],

    'status' => [
        'pending' => '新報修',
        'assigned' => '已派工',
        'in_progress' => '處理中',
        'pending_review' => '待驗收',
        'completed' => '已結案',
    ],

    'create' => [
        'title' => '新增報修案件',
        'from_kb_notice' => '承接自知識庫「:title」，已排除步驟仍無法解決，請補充下面資訊送出報修。',
        'title_label' => '報修標題',
        'device_note_label' => '設備位置／描述（例如：A101 教室投影機）',
        'device_note_hint' => '設備主檔尚未合併進來，暫時用文字描述；之後會改成下拉選單。',
        'location_label' => '地點（選填）',
        'description_label' => '故障描述',
        'description_prefill' => "已依「:title」的排除步驟嘗試過，仍無法解決：\n",
        'impact_level_label' => '影響程度',
        'affects_class_label' => '目前正影響上課',
        'attachments_label' => '故障照片／影片（選填，最多 5 個檔案，jpg/png/pdf/mp4/mov/webm，單檔 20MB 以內）',
        'submit' => '送出報修',
    ],

    'show' => [
        'back_to_board' => '返回看板',
        'device_location_prefix' => '設備／地點：',
        'impact_level_prefix' => '影響程度：',
        'affects_class_prefix' => '是否影響上課：',
        'status_prefix' => '案件狀態：',
        'assignee_prefix' => '維修人員：',
        'scheduled_suffix' => '（預計 :datetime 處理）',
        'submitted_prefix' => '送出時間：',
        'last_rejection_reason_prefix' => '上次驗收退回原因：',
        'description_heading' => '故障描述',
        'attachments_heading' => '報修附件',

        'dispatch_heading' => '派工',
        'assignee_field_label' => '維修人員（users 表尚未合併，先用文字記錄）',
        'scheduled_field_label' => '預計處理日期（選填）',
        'confirm_dispatch' => '確認派工',
        'start_processing' => '開始處理',
        'fill_repair_log' => '填寫維修紀錄',

        'reassign_summary' => '重新指派維修人員',
        'reassign_to_label' => '改指派給（維修人員）',
        'confirm_reassign' => '確認重新指派',

        'acceptance_heading' => '驗收',
        'accept_pass' => '驗收通過，結案',
        'accept_fail_summary' => '驗收不通過，退回重新處理',
        'rejection_reason_label' => '退回原因（維修人員會看到，請具體說明還有什麼問題）',
        'confirm_reject' => '確認退回',

        'repair_logs_heading' => '維修紀錄',
        'processing_time_prefix' => '處理時間：',
        'total_hours_suffix' => '（共 :hours 小時）',
        'cause_prefix' => '故障原因：',
        'resolution_prefix' => '處置方式：',
        'parts_used_prefix' => '使用備品：',
        'log_attachments_prefix' => '維修前後照片／影片：',
    ],

    'flash' => [
        'submitted' => '報修案件已送出。',
        'dispatched' => '已派工。',
        'reassigned' => '已重新指派。',
        'started' => '已標記為處理中。',
        'completed' => '已驗收結案。',
        'rejected' => '已退回重新處理。',
    ],

    'errors' => [
        'invalid_transition' => '無法把案件從「:from」轉成「:to」，不符合合法的狀態流程。',
        'reassign_invalid_status' => '案件狀態是「:status」，不是「已派工」或「處理中」，不能重新指派。',
    ],
];
