{{-- 主檔頁首的「統計圖表」按鈕：點了開出圖表視窗（見 charts/_modal，兩個要一起放進頁面）。圖表不佔版面，平常是收起來的。 --}}
{{-- $buttonClass：按鈕樣式（風格 B 的列表頁傳 tb-btn tb-btn-outline）。
     tb-chart-btn：手機版只顯示圖示（圓形按鈕，跟列表的修改／停用按鈕一樣），文字藏起來（aria-label 仍有「統計圖表」），樣式在 theme-b.css。 --}}
<button type="button" class="{{ $buttonClass ?? 'btn btn-outline-secondary' }} tb-chart-btn" data-bs-toggle="modal" data-bs-target="#masterChartModal"
    title="{{ __('dashboard.charts.open') }}" aria-label="{{ __('dashboard.charts.open') }}">
    <i class="bi bi-bar-chart-line me-1" aria-hidden="true"></i><span class="tb-chart-label">{{ __('dashboard.charts.open') }}</span>
</button>
