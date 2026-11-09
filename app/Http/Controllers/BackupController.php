<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\DatabaseBackup;
use Illuminate\Http\Request;
use Throwable;

/**
 * 系統管理 → 資料備份（需要 backups.manage；預設只有系統管理員）。
 * 列出備份、立即備份、下載、還原，以及設定保留天數。
 */
class BackupController extends Controller
{
    public function __construct(private DatabaseBackup $backup)
    {
    }

    public function index()
    {
        return view('backups.index', [
            'backups' => $this->backup->all(),
            'keepDays' => $this->backup->keepDays(),
        ]);
    }

    /** 立即備份。 */
    public function store()
    {
        $name = $this->backup->create('manual');
        $this->backup->prune();
        AuditLogger::log('created', null, ['file' => $name], "手動備份資料庫：{$name}");

        return redirect()->route('backups.index')->with('success', '已完成備份。');
    }

    public function download(string $name)
    {
        $path = $this->backup->path($name) ?? abort(404);

        return response()->download($path, $name);
    }

    /** 還原到這份備份（還原前會先自動備份目前的資料）。 */
    public function restore(string $name)
    {
        abort_unless($this->backup->path($name), 404);

        try {
            $safety = $this->backup->restore($name);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('backups.index')->with('error', '還原失敗，資料沒有變更：' . $e->getMessage());
        }

        AuditLogger::log('restored', null, ['file' => $name, 'before_restore' => $safety], "還原資料庫：{$name}");

        return redirect()->route('backups.index')
            ->with('success', "已還原到這份備份。還原前的資料也先備份了（{$safety}），需要時可以再還原回來。");
    }

    public function settings(Request $request)
    {
        $validated = $request->validate([
            'keep_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ], [
            'keep_days.required' => '請填寫保留天數。',
            'keep_days.integer' => '保留天數要是整數。',
            'keep_days.min' => '保留天數不能小於 0。',
            'keep_days.max' => '保留天數最多 3650 天。',
        ]);
        $this->backup->setKeepDays((int) $validated['keep_days']);
        AuditLogger::log('updated', null, $validated, '修改備份保留天數：' . $validated['keep_days'] . ' 天');

        return redirect()->route('backups.index')->with('success', '已更新保留天數。');
    }
}
