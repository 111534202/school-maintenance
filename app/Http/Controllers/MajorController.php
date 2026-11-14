<?php

namespace App\Http\Controllers;

use App\Models\Major;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 科系主檔：新增、列表／篩選、修改、啟用／停用、刪除。
 * 空間申請的「科系 → 年級 → 班級」下拉選單從這張表讀（只列啟用中的科系）。
 * 路由整組需要 majors.manage 權限（見 routes/web.php）。
 *
 * 每個科系各自設定「年級數」（五專、大學部共用，所以不是全校統一）與「每個年級的班數」（1～4 班，依序忠、孝、仁、愛）。
 * 刪除規則：還有空間申請選過這個科系時不能刪除，請改用「停用」讓它從下拉選單消失但保留歷史資料。
 *
 * 【想新增科系欄位】migration 加欄位 → Models/Major.php 的 $fillable → 本檔 validated() 加規則
 *   → resources/views/majors/_form.blade.php 加輸入框 → lang/各語言資料夾/majors.php 加文字。
 */
class MajorController extends Controller
{
    /** 科系列表（GET /majors）：可用關鍵字（名稱／簡稱／代碼）與啟用狀態篩選，每頁 15 筆。 */
    public function index(Request $request)
    {
        $majors = Major::query()
            ->withCount('reservations')
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = $request->string('keyword')->toString();
                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('abbreviation', 'like', "%{$keyword}%")
                        ->orWhere('major_code', 'like', "%{$keyword}%");
                });
            })
            ->when($request->string('status')->toString() === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->string('status')->toString() === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('majors.index', compact('majors'));
    }

    /** 顯示新增科系表單（GET /majors/create）。 */
    public function create()
    {
        return view('majors.create');
    }

    /** 接收新增表單（POST /majors）。 */
    public function store(Request $request)
    {
        $major = Major::create($this->validated($request));

        AuditLogger::log('created', $major, $major->only(['name', 'abbreviation', 'grade_count', 'class_count', 'is_active']));

        return redirect()->route('majors.index')->with('success', __('majors.flash.created'));
    }

    /** 顯示編輯表單（GET /majors/{major}/edit）。 */
    public function edit(Major $major)
    {
        return view('majors.edit', compact('major'));
    }

    /** 接收編輯表單（PUT /majors/{major}）。 */
    public function update(Request $request, Major $major)
    {
        $major->update($this->validated($request, $major));

        AuditLogger::log('updated', $major, $major->only(['name', 'abbreviation', 'grade_count', 'class_count', 'is_active']));

        return redirect()->route('majors.index')->with('success', __('majors.flash.updated'));
    }

    /** 啟用／停用切換（列表上的快速按鈕）。停用後不出現在下拉選單，舊的空間申請不受影響。 */
    public function toggle(Major $major): RedirectResponse
    {
        $major->update(['is_active' => ! $major->is_active]);

        AuditLogger::log('status_changed', $major, ['is_active' => $major->is_active]);

        return back()->with('success', $major->is_active ? __('majors.flash.activated') : __('majors.flash.deactivated'));
    }

    /** 刪除科系（DELETE /majors/{major}）。還有空間申請選過這個科系就擋下來，請改用停用。 */
    public function destroy(Major $major): RedirectResponse
    {
        $used = $major->reservations()->count();

        if ($used > 0) {
            return back()->with('error', __('majors.errors.in_use', ['count' => $used]));
        }

        $major->delete();

        AuditLogger::log('deleted', $major, $major->only(['name', 'abbreviation']));

        return redirect()->route('majors.index')->with('success', __('majors.flash.deleted'));
    }

    /**
     * 新增／編輯共用的驗證規則。編輯時傳入 $major，唯一性檢查會排除它自己。
     * 年級數 1～9、班數 1～4（班名只有忠孝仁愛四個）。
     */
    private function validated(Request $request, ?Major $major = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('majors', 'name')->ignore($major?->id)],
            'major_code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('majors', 'major_code')->ignore($major?->id)],
            'abbreviation' => ['required', 'string', 'max:10'],
            'grade_count' => ['required', 'integer', 'min:1', 'max:' . Major::MAX_GRADES],
            'class_count' => ['required', 'integer', 'min:1', 'max:' . count(Major::SECTION_NAMES)],
            'is_active' => ['required', 'boolean'],
        ], [
            'grade_count.min' => '年級數至少要 1 個年級。',
            'grade_count.max' => '年級數最多 ' . Major::MAX_GRADES . ' 個年級。',
            'class_count.min' => '每個年級至少要有 1 個班。',
            'class_count.max' => '每個年級最多 ' . count(Major::SECTION_NAMES) . ' 個班（班名只有忠、孝、仁、愛）。',
        ]);
    }
}
