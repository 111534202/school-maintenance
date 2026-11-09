<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RepairCostAnalysisExport implements Export, WithMultipleSheets
{
    public function __construct(
        private array $data
    ) {}

    public function sheets(): array
    {
        return array_filter([
            new RepairCostSummarySheet($this->data),
            new RepairCostPartsSheet($this->data),
            new RepairCostDetailsSheet($this->data),
            // 篩選「顯示報廢損失」時多一張報廢明細。
            ($this->data['includeScraps'] ?? false) ? new RepairCostScrapsSheet($this->data) : null,
        ]);
    }
}