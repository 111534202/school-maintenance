<?php

namespace App\Services\Import;

use App\Models\Classroom;
use App\Models\Device;
use App\Support\EquipmentCode;
use App\Models\DeviceCategory;
use App\Models\ImportBatch;
use App\Models\MaintenanceOrder;
use App\Services\AuditLogger;
use App\Services\DeviceStatusService;

/**
 * 設備批量匯入。欄位：設備編號*、資產編號、類別*（類別名稱）、品牌、型號、序號、保固到期日、教室*（教室代碼）、狀態、核心設備。
 * 規則和「新增設備」表單完全一樣（編號不可重複、類別與教室必須已存在、停用的教室不能放新設備），
 * 寫入時一樣會寫操作紀錄、並重新計算教室的設備異常標記。
 */
class DeviceImporter extends BulkImporter
{
    public function type(): string
    {
        return 'devices';
    }

    public function label(): string
    {
        return '設備';
    }

    public function permission(): string
    {
        return 'devices.manage';
    }

    public function columns(): array
    {
        return [
            ['key' => 'device_code', 'label' => '設備編號', 'required' => true, 'example' => 'DEV-0101', 'note' => '限英文字母、數字、「-」、「_」、「.」，最多 20 個字；不能和其他設備編號、零件編號重複（含已停用的設備）。'],
            ['key' => 'asset_code', 'label' => '資產編號', 'required' => false, 'example' => '3140101-0001', 'note' => '學校的財產編號，可不填。'],
            ['key' => 'category', 'label' => '類別', 'required' => true, 'example' => '投影機', 'note' => '填「設備類別」主檔裡已有的名稱（見「類別一覽」工作表）。'],
            ['key' => 'brand', 'label' => '品牌', 'required' => false, 'example' => 'Epson', 'note' => '可不填。'],
            ['key' => 'model', 'label' => '型號', 'required' => false, 'example' => 'EB-X06', 'note' => '可不填。'],
            ['key' => 'serial_number', 'label' => '序號', 'required' => false, 'example' => 'SN123456', 'note' => '可不填。'],
            ['key' => 'warranty_end_date', 'label' => '保固到期日', 'required' => false, 'example' => '2027-12-31', 'note' => '格式 2027-12-31 或 2027/12/31，可不填。'],
            ['key' => 'classroom', 'label' => '教室', 'required' => true, 'example' => 'A101', 'note' => '填教室代碼（見「教室一覽」工作表）；停用的教室不能放新設備。'],
            ['key' => 'status', 'label' => '狀態', 'required' => false, 'example' => '正常', 'note' => '正常、已淘汰、停用；不填就是「正常」。（待維修由報修單自動判斷，不用填）'],
        ];
    }

    public function referenceTables(): array
    {
        return [
            ['title' => '類別一覽', 'headings' => ['類別名稱'], 'rows' => \App\Support\StrokeOrder::sort(DeviceCategory::pluck('name'), fn ($n) => $n)->map(fn ($n) => [$n])->all()],
            ['title' => '教室一覽', 'headings' => ['教室代碼', '教室名稱', '是否啟用'], 'rows' => Classroom::orderBy('classroom_code')->get()->map(fn ($c) => [$c->classroom_code, $c->name, $c->is_active ? '是' : '否'])->all()],
        ];
    }

    protected function checkRow(array $row, array &$seen): array
    {
        $errors = [];
        $data = [];

        // 設備編號：必填、不重複（資料庫含軟刪除的、檔案內前面幾列）。
        $code = $row['device_code'] ?? '';
        if ($code === '') {
            $errors[] = '設備編號必填';
        } elseif (! EquipmentCode::validFormat($code) || in_array($code, EquipmentCode::RESERVED, true)) {
            $errors[] = rtrim(EquipmentCode::formatMessage('設備編號'), '。');
        } elseif (isset($seen['codes'][mb_strtolower($code)])) {
            $errors[] = "設備編號「{$code}」在檔案裡重複（第 {$seen['codes'][mb_strtolower($code)]} 列也用了）";
        } elseif ($conflict = EquipmentCode::conflict($code)) {
            $errors[] = rtrim($conflict, '。');   // 和設備或零件編號重複（含已停用的設備）
        }
        $seen['codes'][mb_strtolower($code)] ??= $seen['__line'] ?? '?';
        $data['device_code'] = $code;

        foreach (['asset_code' => '資產編號', 'brand' => '品牌', 'model' => '型號', 'serial_number' => '序號'] as $key => $label) {
            $value = $row[$key] ?? '';
            if (mb_strlen($value) > 255) {
                $errors[] = "{$label}太長（最多 255 字）";
            }
            $data[$key] = $value === '' ? null : $value;
        }

        // 類別：用名稱對照主檔（不分大小寫）。
        $categoryName = $row['category'] ?? '';
        if ($categoryName === '') {
            $errors[] = '類別必填';
        } else {
            $category = $this->categories()[mb_strtolower($categoryName)] ?? null;
            $category ? $data['device_category_id'] = $category : $errors[] = "找不到類別「{$categoryName}」（請先到「設備類別」主檔新增）";
        }

        // 教室：用教室代碼對照主檔，而且要是啟用中的。
        $roomCode = $row['classroom'] ?? '';
        if ($roomCode === '') {
            $errors[] = '教室必填';
        } else {
            $room = $this->classrooms()[mb_strtolower($roomCode)] ?? null;
            if (! $room) {
                $errors[] = "找不到教室代碼「{$roomCode}」";
            } elseif (! $room['active']) {
                $errors[] = "教室「{$roomCode}」已停用，不能放新設備";
            } else {
                $data['classroom_id'] = $room['id'];
            }
        }

        // 狀態：中文名稱或英文代碼都可以，不填＝正常。
        $statusText = $row['status'] ?? '';
        $status = $statusText === '' ? 'normal' : ($this->statuses()[mb_strtolower($statusText)] ?? null);
        $status ? $data['status'] = $status : $errors[] = "狀態「{$statusText}」看不懂（可填：正常、已淘汰、停用；待維修由報修單自動判斷）";

        $date = $this->toDate($row['warranty_end_date'] ?? '');
        $date === false ? $errors[] = '保固到期日格式不對（請填 2027-12-31）' : $data['warranty_end_date'] = $date?->toDateString();

        return [$errors, $data];
    }

    protected function persist(array $data): void
    {
        $device = new Device($data);
        $device->import_batch_id = $this->batchId;   // 記下是哪一次匯入來的，匯錯時才能整批復原
        $device->save();
        AuditLogger::log('created', $device, $data, "批量匯入設備「{$device->device_code}」");
    }

    public function undo(ImportBatch $batch): array
    {
        $done = 0;
        $kept = 0;
        foreach (Device::where('import_batch_id', $batch->id)->get() as $device) {
            // 已經有報修或保養紀錄的設備不收回（履歷要保留）。
            if ($device->repairRequests()->exists() || MaintenanceOrder::where('device_id', $device->id)->exists()) {
                $kept++;

                continue;
            }
            // 做法和手動「停用設備」相同：狀態改停用、軟刪除（資料保留）。
            DeviceStatusService::updateStatus($device, 'disabled', '批量匯入復原');
            $device->delete();
            AuditLogger::log('deleted', $device, [], "批量匯入復原：停用設備「{$device->device_code}」");
            $done++;
        }
        return ['done' => $done, 'kept' => $kept];
    }

    /** @return array<string, int> 類別名稱（小寫）=> 編號 */
    private function categories(): array
    {
        return $this->cache['categories'] ??= DeviceCategory::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])->all();
    }

    /** @return array<string, array{id: int, active: bool}> */
    private function classrooms(): array
    {
        return $this->cache['classrooms'] ??= Classroom::get()->mapWithKeys(fn ($c) => [mb_strtolower($c->classroom_code) => ['id' => $c->id, 'active' => (bool) $c->is_active]])->all();
    }

    /** @return array<string, string> 狀態文字（中文名稱或代碼，小寫）=> 狀態代碼 */
    private function statuses(): array
    {
        $map = [];
        foreach (Device::STATUSES as $status) {
            $map[mb_strtolower($status)] = $status;
            $map[mb_strtolower(Device::statusLabel($status))] = $status;
        }

        return $map;
    }

    /** 同一次匯入內重複查主檔的快取。 */
    private array $cache = [];
}
