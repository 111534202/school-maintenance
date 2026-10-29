<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Models\PreventiveCandidate;
use App\Services\AI\PreventiveCandidateService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * AI 預防保養候選審核（第 4 週任務 2、3）。路由已限定 admin / it_manager。
 */
class PreventiveCandidateController extends Controller
{
    public function index(Request $request): View
    {
        $candidates = PreventiveCandidate::query()
            ->with(['device.classroom', 'decider', 'maintenanceOrder'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('preventive_candidates.index', [
            'candidates' => $candidates,
            'settings' => AiSetting::current(),
        ]);
    }

    public function scan(PreventiveCandidateService $service): RedirectResponse
    {
        $summary = $service->scan();

        $message = sprintf(
            '掃描完成：共 %d 台，新候選 %d 筆、自動建單 %d 筆；資料不足 %d、未達門檻 %d、重複略過 %d。',
            $summary['scanned'],
            $summary['candidates_created'],
            $summary['orders_created'],
            $summary['skipped_insufficient_data'],
            $summary['skipped_below_threshold'],
            $summary['skipped_duplicate'],
        );

        return redirect()->route('preventive-candidates.index')->with('success', $message);
    }

    public function approve(Request $request, PreventiveCandidate $preventiveCandidate, PreventiveCandidateService $service): RedirectResponse
    {
        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:255']]);

        try {
            $order = $service->approve($preventiveCandidate, $request->user(), $data['decision_note'] ?? null);
        } catch (DomainException $e) {
            return redirect()->route('preventive-candidates.index')->with('error', $e->getMessage());
        }

        if ($order === null) {
            return redirect()->route('preventive-candidates.index')
                ->with('error', '這台設備已有即將到期或近期建立的保養工單，為避免重複，未建立新工單。');
        }

        return redirect()->route('preventive-candidates.index')
            ->with('success', "已核准，並建立 AI 保養工單 #{$order->id}。");
    }

    public function reject(Request $request, PreventiveCandidate $preventiveCandidate, PreventiveCandidateService $service): RedirectResponse
    {
        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:255']]);

        try {
            $service->reject($preventiveCandidate, $request->user(), $data['decision_note'] ?? null);
        } catch (DomainException $e) {
            return redirect()->route('preventive-candidates.index')->with('error', $e->getMessage());
        }

        return redirect()->route('preventive-candidates.index')->with('success', '已駁回這筆候選。');
    }
}
