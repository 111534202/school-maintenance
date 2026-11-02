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
        return [
            new RepairCostSummarySheet($this->data),
            new RepairCostPartsSheet($this->data),
            new RepairCostDetailsSheet($this->data),
        ];
    }
}