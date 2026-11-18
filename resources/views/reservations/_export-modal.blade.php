{{-- 空間申請匯出視窗：先預覽，勾選要匯出的欄位（上方）與申請（每一列左邊），再按「匯出 Excel」。
     申請範圍＝目前搜尋與狀態篩選下、你看得到的申請（有審核權限的人是全部，其他人只有自己提出的；最多列 500 筆）。
     視窗本身用列表共用的 layouts/partials/export-preview。
     用法：@include('reservations._export-modal', ['exportReservations' => $exportReservations]) --}}
@include('layouts.partials.export-preview', [
    'modalId' => 'reservationExportModal',
    'title' => '匯出空間申請',
    'action' => route('reservations.export'),
    'columnsClass' => \App\Support\ReservationExportColumns::class,
    'items' => $exportReservations,
    'total' => $exportTotal ?? null,
    'unit' => '筆申請',
])
