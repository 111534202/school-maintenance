<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * AI 預防保養參數（第 4 週任務 3）。
 *
 * 資料庫沒有該筆設定時一律退回 DEFAULTS，所以不跑 seeder 也能正常運作。
 * 以下預設值皆為「工程實作決定，待全組確認」，管理員可在「AI 預防保養設定」頁調整。
 */
#[Fillable(['key', 'value'])]
class AiSetting extends Model
{
    public const RISK_THRESHOLD = 'risk_threshold';

    public const MIN_COMPLETED_ORDERS = 'min_completed_orders';

    public const RECENT_WINDOW = 'recent_window';

    public const DEDUP_WINDOW_DAYS = 'dedup_window_days';

    public const REQUIRE_APPROVAL = 'require_approval';

    /**
     * @var array<string, float|int|bool>
     */
    public const DEFAULTS = [
        self::RISK_THRESHOLD => 0.5,       // 風險分數達此值就提出預防保養候選
        self::MIN_COMPLETED_ORDERS => 4,   // 已回報結果的保養紀錄少於此筆數，不做預測（資料不足）
        self::RECENT_WINDOW => 6,          // 只看最近幾筆保養結果來算 NG 比例
        self::DEDUP_WINDOW_DAYS => 14,     // 去重視窗：這幾天內已有（或即將有）保養就不重複提出
        self::REQUIRE_APPROVAL => true,    // true：先產生候選等主管審核；false：直接建立 AI 工單
    ];

    public static function get(string $key): float|int|bool
    {
        $default = self::DEFAULTS[$key];
        $stored = static::query()->where('key', $key)->value('value');

        if ($stored === null) {
            return $default;
        }

        return match (true) {
            is_bool($default) => filter_var($stored, FILTER_VALIDATE_BOOLEAN),
            is_int($default) => (int) $stored,
            default => (float) $stored,
        };
    }

    /**
     * @return array<string, float|int|bool>
     */
    public static function current(): array
    {
        $values = [];

        foreach (array_keys(self::DEFAULTS) as $key) {
            $values[$key] = self::get($key);
        }

        return $values;
    }

    public static function put(string $key, float|int|bool $value): void
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            throw new \InvalidArgumentException("未知的 AI 設定：{$key}");
        }

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]
        );
    }
}
