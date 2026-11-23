{{-- 維修案件看板（報修單列表，對應 RepairRequestController::index）：狀態／地點／維修人員篩選 + 資料表格 + 分頁。 --}}
{{-- 主控台的圖表、通知鈴鐺的連結會帶 ?status=xxx 或 ?assignee=xxx 進到這一頁。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', __('repair_requests.board_title'))

@section('content')
<div class="tb">
    {{-- 在對話框裡打開「我的報修」（?mine=1&modal=1）時：標題已經在對話框上方，頁首、篩選列與右下角匯出都不顯示，只列自己的報修單。 --}}
    @php
        $inModal = request()->boolean('modal');
        // 沒有派工、維修、驗收權限的人：看板就是「我的報修」（標題改成我的報修，不放「我的報修」按鈕與「範圍」篩選）。
        $onlyOwn = \App\Http\Controllers\RepairRequestController::onlyOwnRepairs(auth()->user());
    @endphp
    <div class="tb-header" @if ($inModal) data-modal-hide @endif>
        <div>
            <h1 class="tb-title">{{ $onlyOwn ? __('common.nav.my_repairs') : __('repair_requests.board_title') }}</h1>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-end">
            {{-- 我的報修：像空間申請的「我的申請」，在畫面中的對話框打開自己提出的報修單（表格／清單自動切換）。 --}}
            @unless ($onlyOwn)
            <a href="{{ route('repairs.index', ['mine' => 1]) }}" class="tb-btn tb-btn-outline" data-modal-frame data-modal-table data-modal-title="{{ __('common.nav.my_repairs') }}"><i class="bi bi-person-lines-fill" aria-hidden="true"></i>{{ __('common.nav.my_repairs') }}</a>
            @endunless
        {{-- 有「提出報修」權限才顯示「新增報修」按鈕。 --}}
        @can('repairs.create')
            <a class="tb-btn tb-btn-primary" href="{{ route('repairs.create') }}" data-modal-frame data-modal-title="{{ __('repair_requests.add_request') }}" data-modal-size="xl">
                <i class="bi bi-plus-lg"></i>{{ __('repair_requests.add_request') }}
            </a>
        @endcan
        </div>
    </div>

    {{-- 篩選列：直接顯示在頁面上（data-no-onion），放得下就排成一列，不夠寬時換行（tb-filter，不用左右捲動），按鈕一律用圖示。 --}}
    @unless ($inModal)
    <div class="tb-card tb-card-pad">
        <div>
            <form method="GET" action="{{ route('repairs.index') }}" class="filter-bar tb-form-row tb-filter" data-no-onion>
                <div>
                    <label for="keyword" class="form-label small mb-1">關鍵字</label>
                    <input type="text" id="keyword" name="keyword" class="form-control form-control-sm" style="width: 15rem;"
                        value="{{ request('keyword') }}" placeholder="標題、設備編號、教室、報修人、維修人員、編號">
                </div>
                <div>
                    <label for="status" class="form-label small mb-1">{{ __('repair_requests.filter.status') }}</label>
                    @php
                        // 目前的狀態篩選（逾時、單一狀態，或主控台帶來的好幾個狀態，例如「已派工、待驗收」）。
                        $statusFilter = \App\Http\Controllers\RepairRequestController::boardStatusFilter(request());
                        $statusValue = implode(',', $statusFilter);
                    @endphp
                    <select id="status" name="status" class="form-select form-select-sm" style="width: 9rem;">
                        <option value="">{{ __('repair_requests.filter.status_all') }}</option>
                        {{-- 主控台卡片帶來的好幾個狀態：多一個選項顯示「新報修、已派工、待驗收」並選起來，篩選列看得出目前的範圍。 --}}
                        @if (count($statusFilter) > 1)
                            <option value="{{ $statusValue }}" selected>{{ collect($statusFilter)->map(fn ($s) => \App\Enums\RepairRequestStatus::from($s)->label())->implode('、') }}</option>
                        @endif
                        {{-- 沒有驗收權限的人下拉選單沒有「已結案」，但從主控台工時卡片連過來時狀態就是已結案：補一個選項，按篩選才不會被洗掉。 --}}
                        @if ($statusValue === 'completed' && ! auth()->user()->can('repairs.accept'))
                            <option value="completed" selected>{{ \App\Enums\RepairRequestStatus::Completed->label() }}</option>
                        @endif
                        {{-- 狀態下拉選單：列出所有案件狀態（新報修、已派工、已退回、待驗收、已結案）。 --}}
                        @foreach ($statuses as $statusOption)
                            {{-- 沒有驗收權限的人，看板的「待驗收」「已結案」選項整個隱藏。 --}}
                            @continue(in_array($statusOption->value, ['pending_review', 'completed'], true) && ! auth()->user()->can('repairs.accept'))
                            <option value="{{ $statusOption->value }}" @selected($statusValue === $statusOption->value)>
                                {{ $statusOption->label() }}
                            </option>
                        @endforeach
                        {{-- 逾時：未結案且超過預計處理時間（主控台「逾時工單」卡片連過來就是選這個）。 --}}
                        <option value="overdue" @selected($statusValue === 'overdue')>{{ __('repair_requests.overdue') }}</option>
                    </select>
                </div>
                <div>
                    <label for="classroom_id" class="form-label small mb-1">{{ __('repair_requests.filter.classroom') }}</label>
                    {{-- 教室下拉選單：只看「這間教室裡的設備」的報修單（教室主檔的「進行中工單」數字會連到這裡）。 --}}
                    <select id="classroom_id" name="classroom_id" class="form-select form-select-sm" style="width: 12rem;">
                        <option value="">{{ __('repair_requests.filter.classroom_all') }}</option>
                        @foreach ($classrooms as $classroom)
                            <option value="{{ $classroom->id }}" @selected((string) request('classroom_id') === (string) $classroom->id)>{{ $classroom->classroom_code }} - {{ $classroom->name }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- 維修人員併進關鍵字搜尋；主控台等連結帶來的 ?assignee= 照樣保留。 --}}
                @if (request()->filled('assignee'))
                    <input type="hidden" name="assignee" value="{{ request('assignee') }}">
                @endif
                @unless ($onlyOwn)
                <div>
                    <label for="mine" class="form-label small mb-1">範圍</label>
                    <select id="mine" name="mine" class="form-select form-select-sm" style="width: 9rem;">
                        <option value="">全部看得到的</option>
                        <option value="1" @selected(request()->boolean('mine'))>我的報修</option>
                    </select>
                </div>
                @endunless
                {{-- 從設備主檔連過來時網址帶有 device_id；按「篩選」時要一併帶著，才不會篩一次就跑掉。 --}}
                @if (request('device_id'))
                    <input type="hidden" name="device_id" value="{{ request('device_id') }}">
                @endif
                {{-- 其他從主控台、教室主檔、通知鈴鐺連過來的進階篩選（新增日期範圍、完修日期、設備所在教室、設備類別、指派給我、等我驗收），
                     按「篩選」時同樣要帶著，不然一篩選就被洗掉。（?open=1 已經換成狀態欄的「新報修、已派工、待驗收」，不另外帶。） --}}
                @foreach (['created_from', 'created_to', 'device_classroom_id', 'device_category_id', 'assigned_to_me', 'acceptable', 'ended_from', 'ended_to'] as $carry)
                    @if (request()->filled($carry))
                        <input type="hidden" name="{{ $carry }}" value="{{ request($carry) }}">
                    @endif
                @endforeach
                @include('layouts.partials.filter-actions', ['reset' => route('repairs.index'), 'fields' => ['keyword', 'status', 'assignee', 'classroom_id', 'device_id', 'overdue', 'mine', 'open', 'created_from', 'created_to', 'device_classroom_id', 'device_category_id', 'assigned_to_me', 'acceptable', 'ended_from', 'ended_to']])
            </form>
        </div>
    </div>
    @endunless

    {{-- 進階篩選的說明（篩選列沒有對應欄位的那幾個：新增日期、完修日期、設備所在教室、設備類別、指派給我、等我驗收）：
         一行灰字一項，附清除連結。狀態（含逾時、好幾個狀態）直接顯示在篩選列的狀態欄，不另外說明。 --}}
    @foreach ($activeFilters as $activeFilter)
        <div class="text-muted small d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <span><i class="bi bi-funnel me-2"></i>{{ $activeFilter['text'] }}</span>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('repairs.index', request()->except(array_merge($activeFilter['clear'], ['page']))) }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.clear_filter') }}</a>
        </div>
    @endforeach

    {{-- 從設備主檔連過來（?device_id=）時，提醒「目前只看這台設備」，並提供清除的連結。 --}}
    @if ($filteredDevice)
        <div class="text-muted small d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <span><i class="bi bi-pc-display me-2"></i>{{ __('repair_requests.filter.only_device', ['device' => $filteredDevice->device_code]) }}</span>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('repairs.index', request()->except(['device_id', 'page'])) }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.clear_filter') }}</a>
        </div>
    @endif

    {{-- 查不到任何案件時顯示提示；否則顯示表格。 --}}
    @if ($repairRequests->isEmpty())
        <div class="tb-card tb-empty">{{ __('repair_requests.empty_list') }}</div>
    @else
        <section class="tb-table-wrap" data-no-fit>
            <div class="table-responsive">
                {{-- 資料表格（有框線）。 --}}
                <table class="tb-table tb-table-hover" data-onion-primary="1,5">
                    <thead>
                        <tr>
                            <th>{{ __('repair_requests.table.title') }}</th>
                            <th>{{ __('repair_requests.table.device_location') }}</th>
                            <th class="text-center">{{ __('repair_requests.table.impact_level') }}</th>
                            <th class="text-center">{{ __('repair_requests.table.is_class_affected') }}</th>
                            <th class="text-center">{{ __('repair_requests.table.status') }}</th>
                            <th>{{ __('repair_requests.table.assignee') }}</th>
                            <th>{{ __('repair_requests.table.submitted_at') }}</th>
                            <th>{{ __('repair_requests.table.waiting_time') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- 逐一列出每張報修單。 --}}
                        @foreach ($repairRequests as $repairRequest)
                            @php
                                // 影響程度 → 徽章顏色：輕微綠、中等黃、嚴重紅；未知值用灰色。
                                $impactBadge = ['low' => 'text-bg-success', 'medium' => 'text-bg-warning', 'high' => 'text-bg-danger'][$repairRequest->impact_level] ?? 'text-bg-secondary';
                            @endphp
                            {{-- 點這一列的任何地方都會在畫面中的對話框打開案件詳細（data-modal-frame-href，行為在共用版型）。 --}}
                            @php($repeatRepair = $repairRequest->device && ($repairRequest->device->recent_repairs_count ?? 0) >= \App\Models\Device::REPEAT_REPAIR_THRESHOLD)
                            {{-- 重複報修的設備：手機版清單不另外列「近30天報修N次」，改成標題用紅色粗體字（data-onion-alert）。 --}}
                            <tr data-modal-frame-href="{{ route('repairs.show', $repairRequest) }}" data-modal-title="報修案件" data-modal-size="xl" tabindex="0" role="link"
                                @if ($repeatRepair) data-onion-alert="{{ __('devices.repeat_repair_badge', ['count' => $repairRequest->device->recent_repairs_count]) }}" @endif>
                                <td class="fw-semibold">
                                    {{ $repairRequest->title }}
                                    {{-- 重複報修提醒：這台設備近 30 天報修達到門檻次數，標題下面紅色粗體字；點了用關鍵字（設備編號）篩選看板；只有文字本身能點（不是整格），點標題旁邊的空白不會誤跳。 --}}
                                    @if ($repeatRepair)
                                        <div data-onion-skip><a href="{{ route('repairs.index', ['keyword' => $repairRequest->device->device_code]) }}" class="small text-danger fw-bold text-decoration-none"
                                            title="{{ __('devices.repeat_repair_hint') }}">{{ __('devices.repeat_repair_badge', ['count' => $repairRequest->device->recent_repairs_count]) }}</a></div>
                                    @endif
                                </td>
                                {{-- 設備欄：有綁定設備就顯示設備編號，否則顯示手填的設備描述；下面一行是地點（報修單的發現地點，沒有就用設備所在教室）。 --}}
                                @php($rowClassroom = $repairRequest->classroom ?? $repairRequest->device?->classroom)
                                <td>
                                    {{ $repairRequest->device->device_code ?? $repairRequest->device_note ?? ($rowClassroom ? '' : __('repair_requests.not_filled')) }}
                                    @if ($rowClassroom)
                                        <div class="small text-muted">{{ $rowClassroom->classroom_code }} {{ $rowClassroom->name }}</div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $impactBadge }}">{{ __('repair_requests.impact_level.' . $repairRequest->impact_level) }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($repairRequest->is_class_affected)
                                        <span class="badge tb-pill tb-pill-danger">{{ __('repair_requests.yes') }}</span>
                                    @else
                                        <span class="badge tb-pill tb-pill-muted">{{ __('repair_requests.no') }}</span>
                                    @endif
                                </td>
                                {{-- 引入共用的狀態徽章（看板與詳細頁用同一份，顏色集中管理）。 --}}
                                <td class="text-center">
                                    @include('repairs._status-badge', ['status' => $repairRequest->status])
                                    {{-- 逾時提醒：還沒結案、且已經超過預計處理時間（見 RepairRequest::isOverdue）。 --}}
                                    @if ($repairRequest->is_overdue)
                                        <span class="badge tb-pill tb-pill-danger ms-1"><i class="bi bi-alarm"></i> {{ __('repair_requests.overdue') }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{-- 讓主管看到「這個人手上還有幾件沒結案」，跟名字放在同一格，方便判斷要不要再加派；
                                         系統不會自動幫忙排序或推薦人選。 --}}
                                    {{-- 維修人員：優先顯示指派的真實用戶姓名，沒有就用文字備註，再沒有顯示「未指派」。 --}}
                                    {{ $repairRequest->assignedTechnician->name ?? __('repair_requests.unassigned') }}
                                    {{-- 顯示該維修人員「手上還有 N 件未結案」，讓主管判斷是否要再加派（只顯示數字，不自動推薦）。 --}}
                                    @if ($repairRequest->assigned_to && ($activeCaseCountsByAssignee[$repairRequest->assigned_to] ?? 0) > 0)
                                        <span class="text-muted small">{{ __('repair_requests.active_case_suffix', ['count' => $activeCaseCountsByAssignee[$repairRequest->assigned_to]]) }}</span>
                                    @endif
                                </td>
                                <td>{{ $repairRequest->created_at->format('Y-m-d H:i') }}</td>
                                {{-- 等待時間：用「3 小時前」這種口語方式顯示；語言由 SetLocale 中介層決定。 --}}
                                <td>{{ $repairRequest->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- 分頁按鈕；data-modal-stay：在「我的報修」對話框裡按分頁，就在對話框裡換頁。 --}}
        <div @if ($inModal) data-modal-stay @endif>
            @include('layouts.pager', ['paginator' => $repairRequests])
        </div>
    @endif

    @unless ($inModal)
        {{-- 右下角：匯出 Excel（先開預覽視窗，勾選目前搜尋與篩選條件下要匯出的欄位與報修單；只含你看得到的案件）。 --}}
        <div class="tb-bottom-actions">
            <button type="button" class="tb-btn tb-btn-outline" data-bs-toggle="modal" data-bs-target="#repairExportModal"><i class="bi bi-download" aria-hidden="true"></i><span class="tb-label-long">匯出 Excel</span><span class="tb-label-short">匯出</span></button>
        </div>
    @endunless
</div>

@unless ($inModal)
    @include('layouts.partials.export-preview', [
        'modalId' => 'repairExportModal', 'title' => '匯出報修單', 'action' => route('repairs.export'),
        'columnsClass' => \App\Support\RepairExportColumns::class, 'items' => $exportRepairs, 'total' => $repairRequests->total(), 'unit' => '張報修單',
    ])
@endunless
@endsection
