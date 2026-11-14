<?php

// i18n：科系主檔。修改這個檔案時，記得同步修改 lang/en/majors.php，兩邊的 key 必須完全一致。
return [
    'index_title' => '科系主檔',
    'add_major' => '新增科系',
    'empty_list' => '沒有符合條件的科系。',
    'intro' => '空間申請的「科系 → 年級 → 班級」下拉選單從這裡讀。五專與大學部共用，所以每個科系各自設定年級數與班數；班名依序是忠、孝、仁、愛。',

    'filter' => [
        'keyword' => '關鍵字',
        'keyword_placeholder' => '科系名稱／簡稱／代碼',
        'status' => '狀態',
        'status_all' => '全部',
    ],

    'status' => [
        'active' => '啟用中',
        'inactive' => '已停用',
    ],

    'table' => [
        'code' => '科系代碼',
        'name' => '科系名稱',
        'abbreviation' => '簡稱',
        'grades' => '年級數',
        'classes' => '每年級班數',
        'example' => '班級名稱範例',
        'reservations' => '空間申請數',
        'status' => '狀態',
        'actions' => '操作',
    ],

    'actions' => [
        'activate' => '啟用科系',
        'deactivate' => '停用科系',
    ],

    'confirm' => [
        'deactivate' => '確定要停用「:name」嗎？停用後不會再出現在空間申請的科系下拉選單，既有的申請不受影響。',
        'delete' => '請問確定要刪除科系「:name」嗎？',
    ],

    'form' => [
        'create_title' => '新增科系',
        'edit_title' => '編輯科系',
        'code' => '科系代碼',
        'code_hint' => '選填，限英文、數字、點、底線、連字號，不能重複。',
        'name' => '科系名稱',
        'abbreviation' => '簡稱',
        'abbreviation_hint' => '組成班級名稱用，例如「資」→ 資一忠（資訊科一年級忠班）。',
        'grade_count' => '年級數',
        'grade_count_hint' => '這個科系有幾個年級（五專通常 5、大學部通常 4；1～9）。',
        'class_count' => '每個年級的班數',
        'class_count_hint' => '每個年級分幾班（1～4），班名依序是忠、孝、仁、愛。',
        'example' => '班級名稱範例：',
        'is_active' => '啟用（關閉後不會出現在下拉選單）',
    ],

    'flash' => [
        'created' => '科系已新增。',
        'updated' => '科系已更新。',
        'activated' => '科系已啟用。',
        'deactivated' => '科系已停用。',
        'deleted' => '科系已刪除。',
    ],

    'errors' => [
        'in_use' => '已有 :count 筆空間申請選過這個科系，不能刪除。請改用「停用」，它就不會再出現在下拉選單，歷史資料保留。',
    ],
];
