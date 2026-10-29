{{-- AI 風險評分明細（可解釋性）：$explanation 為 RuleBasedPredictionService 回傳的 explanation 陣列。
     說明文字放在項目名稱下方（小字），表格只剩 4 欄，手機上不用左右捲動就能看完。 --}}
<table class="table table-sm table-bordered mb-1 bg-white align-middle">
    <thead class="table-light">
        <tr>
            <th>評分項目</th>
            <th class="text-end">原始值</th>
            <th class="text-end">權重</th>
            <th class="text-end">貢獻</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($explanation as $row)
            <tr>
                <td style="white-space: normal; min-width: 140px;">
                    {{ $row['label'] }}
                    <div class="small text-muted">{{ $row['detail'] }}</div>
                </td>
                <td class="text-end">{{ number_format($row['raw'], 3) }}</td>
                <td class="text-end">{{ number_format($row['weight'], 2) }}</td>
                <td class="text-end">{{ number_format($row['contribution'], 3) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
