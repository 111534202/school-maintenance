{{-- AI 風險評分明細（可解釋性）：$explanation 為 RuleBasedPredictionService 回傳的 explanation 陣列 --}}
<table class="table table-sm table-bordered mb-1 bg-white" style="min-width: 520px;">
    <thead class="table-light">
        <tr>
            <th>評分項目</th>
            <th class="text-end">原始值</th>
            <th class="text-end">權重</th>
            <th class="text-end">貢獻分數</th>
            <th>說明</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($explanation as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="text-end">{{ number_format($row['raw'], 3) }}</td>
                <td class="text-end">{{ number_format($row['weight'], 2) }}</td>
                <td class="text-end">{{ number_format($row['contribution'], 3) }}</td>
                <td>{{ $row['detail'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
