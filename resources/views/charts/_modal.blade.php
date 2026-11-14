{{-- 主檔頁面的統計圖表視窗（資料由 App\Services\MasterCharts 提供，傳進 $chart）。
     點圖上的扇形／長條會跳到對應功能區（網址已經帶好篩選條件）。畫圖用 Chart.js（放在 public/vendor，不連外部網站，離線也能畫圖）。 --}}
<div class="modal fade" id="masterChartModal" tabindex="-1" aria-labelledby="masterChartTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="masterChartTitle"><i class="bi bi-bar-chart-line me-2"></i>{{ $chart['title'] }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('common.buttons.cancel') }}"></button>
            </div>
            <div class="modal-body">
                {{-- 資料全部是 0 就顯示「目前沒有資料」，不畫空圖表。 --}}
                @if (array_sum($chart['counts']) === 0)
                    <div class="text-center text-muted py-5">{{ __('dashboard.charts.no_data') }}</div>
                @else
                    <div style="position: relative; height: min(55vh, 380px);"><canvas id="masterChartCanvas" role="img" aria-label="{{ $chart['title'] }}"></canvas></div>
                @endif
            </div>
        </div>
    </div>
</div>
@if (array_sum($chart['counts']) > 0)
    <script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
    <script>
        (function () {
            var spec = @json($chart);
            var modal = document.getElementById('masterChartModal');
            var drawn = null;
            // 視窗打開後才畫（隱藏中的畫布量不到大小）。
            modal.addEventListener('shown.bs.modal', function () {
                if (drawn) { drawn.resize(); return; }
                if (!window.Chart) { return; }
                var isRound = spec.type === 'doughnut';
                drawn = new Chart(document.getElementById('masterChartCanvas'), {
                    type: spec.type,
                    data: { labels: spec.labels, datasets: [{ data: spec.counts, backgroundColor: spec.colors, borderColor: '#fff', borderWidth: 1, label: spec.series || '' }] },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: isRound, position: 'bottom' } },
                        scales: isRound ? {} : { y: { beginAtZero: true, ticks: { precision: 0 } } },
                        onClick: function (event, elements) { if (elements.length) { window.location.href = spec.urls[elements[0].index]; } },
                        onHover: function (event, elements) { event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
                    },
                });
            });
        })();
    </script>
@endif
