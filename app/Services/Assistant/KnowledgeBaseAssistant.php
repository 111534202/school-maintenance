<?php

namespace App\Services\Assistant;

use App\Models\KnowledgeBase;        // 知識庫文章
use App\Models\RepairRequest;        // 報修單
use App\Models\User;                 // 用戶
use Illuminate\Support\Collection;   // 集合
use Illuminate\Support\Str;          // 文字工具

/**
 * AI 客服的規則式實作：依知識庫自動回答，不連外部網路、不需要金鑰。
 *
 * 判斷順序：
 *   1. 打招呼 → 說明能幫什麼忙
 *   2. 問報修進度 → 只列出「自己提出」的報修單
 *   3. 問操作方式（怎麼報修、借教室、改密碼…）→ 操作說明與按鈕
 *   4. 其他 → 比對「已上架」的知識庫文章
 *   5. 都找不到 → 請使用者換個說法或直接報修
 *
 * 文字內容放在 lang/{語言}/assistant.php，要改用詞改那裡。
 */
class KnowledgeBaseAssistant implements AssistantInterface
{
    /** 低於這個分數就當作「不相關」（滿分 3 分）。 */
    private const MIN_SCORE = 0.75;

    /** 一次最多列出幾篇文章。 */
    private const MAX_ARTICLES = 3;

    /** 一次最多列出幾張報修單。 */
    private const MAX_REPAIRS = 5;

    /** 各欄位的重要程度：標題最重要，排除步驟只當參考。 */
    private const FIELD_WEIGHTS = ['title' => 3.0, 'category' => 2.0, 'symptom' => 2.0, 'solution' => 0.5];

    /** 口語說法 → 知識庫常用的說法（先換掉再比對，問法不同也找得到）。 */
    private const SYNONYMS = [
        'wi-fi' => 'wifi', '開不了機' => '無法開機', '不能開機' => '無法開機', '打不開' => '無法開機',
        '上不了網' => '無法連上網路', '不能上網' => '無法連上網路', '無法上網' => '無法連上網路',
        '沒有網路' => '無法連上網路', '沒網路' => '無法連上網路',
        '黑屏' => '黑畫面', '單槍' => '投影機', '空調' => '冷氣',
    ];

    /** 比對時沒有意義的字（很多問題都會出現，留著會干擾結果）。 */
    private const STOP_WORDS = ['請問', '怎麼辦', '怎麼', '為什麼', '什麼', '如何', '可以', '我的', '我要', '我想', '一直', '壞了', '壞掉', '了', '嗎', '呢', '的', '啊'];

    /** 回答一句話（判斷順序見類別說明）。 */
    public function answer(User $user, string $question): array
    {
        $text = $this->normalize($question);   // 整理過的問題

        // 只有短句才當作打招呼，避免「你好，投影機壞了」被當成招呼而不回答。
        if (mb_strlen($text) <= 6 && $this->mentions($text, 'greeting')) {
            return $this->reply(__('assistant.reply.greeting'), suggestions: __('assistant.suggestions'));
        }
        if ($this->mentions($text, 'progress')) {
            return $this->repairProgress($user, $question);
        }

        $articles = $this->searchArticles($text);
        $guide = $this->guide($user, $text);

        if ($guide !== null) {
            // 想報修時順便附上可能有用的文章（也許自己就能排除）。
            if ($guide['key'] === 'repair' && $articles->isNotEmpty()) {
                return $this->reply($guide['text'] . "\n" . __('assistant.reply.maybe_helpful'), $this->articleItems($articles), $guide['actions']);
            }

            return $this->reply($guide['text'], [], $guide['actions']);
        }
        if ($articles->isNotEmpty()) {
            return $this->reply(__('assistant.reply.found'), $this->articleItems($articles), $this->repairAction($user, $articles->first()->id));
        }

        return $this->reply(__('assistant.reply.not_found'), [], $this->repairAction($user), __('assistant.suggestions'));
    }

    /** 報修進度：只查自己提出的報修單；問題裡有「#編號」就只找那一張（不是自己的一律當作找不到）。 */
    private function repairProgress(User $user, string $question): array
    {
        $query = RepairRequest::query()->where('requester_id', $user->id);
        if (preg_match('/[#＃]\s*(\d{1,9})/u', $question, $match)) {
            $query->whereKey((int) $match[1]);
        }
        $repairs = $query->latest('id')->limit(self::MAX_REPAIRS)->get();
        $actions = [['label' => __('assistant.action.my_repairs'), 'url' => route('repairs.index', ['mine' => 1], false)]];

        if ($repairs->isEmpty()) {
            return $this->reply(__('assistant.reply.no_repairs'), [], [...$this->repairAction($user), ...$actions]);
        }

        $items = $repairs->map(fn (RepairRequest $repair) => [
            'title' => '#' . $repair->id . ' ' . $repair->title,
            'text' => $repair->status->label() . '・' . $repair->created_at?->format('Y-m-d H:i'),
            'url' => route('repairs.show', $repair, false),
        ])->values()->all();

        return $this->reply(__('assistant.reply.progress'), $items, $actions);
    }

    /**
     * 操作說明：問題裡出現某一項的關鍵字就回那一項；按鈕只給有權限的人。
     *
     * @return array{key: string, text: string, actions: list<array{label: string, url: string}>}|null
     */
    private function guide(User $user, string $text): ?array
    {
        foreach (['password', 'accept', 'reservation', 'maintenance', 'human', 'repair'] as $key) {
            if (! $this->mentions($text, $key)) {
                continue;
            }
            // 需要權限的項目：沒有權限就改回「你的身分不能…」，也不給按鈕。
            $allowed = match ($key) {
                'accept' => $user->can('repairs.accept'),
                'maintenance' => $user->can('maintenance.view'),
                'repair' => $user->can('repairs.create'),
                default => true,
            };
            $actions = ! $allowed ? [] : match ($key) {
                'password' => [['label' => __('assistant.action.password'), 'url' => route('password.change', [], false)]],
                'accept' => [['label' => __('assistant.action.my_repairs'), 'url' => route('repairs.index', ['mine' => 1], false)]],
                'reservation' => [['label' => __('assistant.action.reservation'), 'url' => route('reservations.index', [], false)]],
                'maintenance' => [['label' => __('assistant.action.maintenance'), 'url' => route('maintenance-orders.index', [], false)]],
                default => $this->repairAction($user),
            };

            return ['key' => $key, 'text' => __('assistant.guide.' . $key . ($allowed ? '' : '_denied')), 'actions' => $actions];
        }

        return null;
    }

    /**
     * 比對知識庫：把問題與文章切成兩個字一組（英文、數字以單字為單位），看問題裡有多少組出現在文章中。
     * 只有少數文章才有的字組比較有鑑別度，分數較高。草稿一律不拿來回答。
     *
     * @return Collection<int, KnowledgeBase>
     */
    private function searchArticles(string $text): Collection
    {
        $terms = $this->terms($text);
        if ($terms === []) {
            return collect();
        }

        $articles = KnowledgeBase::query()->where('is_published', true)->orderByDesc('view_count')->limit(500)
            ->get(['id', 'title', 'category', 'symptom', 'solution']);

        // 每篇文章、每個欄位各有哪些字組（字組當鍵，查起來快）。
        $sets = [];
        foreach ($articles as $article) {
            foreach (array_keys(self::FIELD_WEIGHTS) as $field) {
                $sets[$article->id][$field] = array_flip($this->terms($this->normalize((string) $article->{$field})));
            }
        }

        // 每個字組的鑑別度：出現在越少文章越高；沒有任何文章出現的也要算進分母（代表問題有一部分對不上）。
        $importance = [];
        foreach ($terms as $term) {
            $seen = 0;
            foreach ($sets as $fields) {
                $seen += array_filter($fields, fn (array $set) => isset($set[$term])) !== [] ? 1 : 0;
            }
            $importance[$term] = log(1 + max(1, count($sets)) / max(1, $seen));
        }
        $total = array_sum($importance);

        $scored = $articles->map(function (KnowledgeBase $article) use ($terms, $sets, $importance, $total) {
            $score = 0.0;
            foreach ($terms as $term) {
                $best = 0.0;   // 這個字組在這篇文章裡出現過的最重要欄位
                foreach (self::FIELD_WEIGHTS as $field => $weight) {
                    $best = isset($sets[$article->id][$field][$term]) ? max($best, $weight) : $best;
                }
                $score += $importance[$term] * $best;
            }

            return ['article' => $article, 'score' => $score / $total];
        })->filter(fn (array $row) => $row['score'] >= self::MIN_SCORE)->sortByDesc('score')->values();

        // 和最相關的那篇差太多的就不列，避免混進不相干的文章。
        $top = $scored->first()['score'] ?? 0.0;

        return $scored->filter(fn (array $row) => $row['score'] >= $top * 0.6)->take(self::MAX_ARTICLES)->pluck('article')->values();
    }

    /** 統一全形半形與大小寫、換掉口語說法、標點換成空白。 */
    private function normalize(string $text): string
    {
        $text = strtr(mb_strtolower(mb_convert_kana($text, 'as')), self::SYNONYMS);

        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text));
    }

    /**
     * 切成比對用的字組：中文兩個字一組，英文與數字整個單字。
     *
     * @return list<string>
     */
    private function terms(string $text): array
    {
        preg_match_all('/[a-z]+[a-z0-9]*|\d{2,}|\p{Han}+/u', str_replace(self::STOP_WORDS, ' ', $text), $parts);
        $terms = [];
        foreach ($parts[0] as $part) {
            if (preg_match('/^[a-z0-9]+$/', $part)) {
                $terms['w' . $part] = true;   // 前面加 w：避免純數字當陣列鍵時變成整數

                continue;
            }
            for ($i = 0; $i < mb_strlen($part) - 1; $i++) {
                $terms[mb_substr($part, $i, 2)] = true;
            }
        }

        return array_keys($terms);
    }

    /** 問題裡有沒有出現某一類的關鍵字（清單在語言檔）。英文用整個單字比對，避免 hi 比中 this。 */
    private function mentions(string $text, string $key): bool
    {
        foreach (__('assistant.keywords.' . $key) as $word) {
            $found = preg_match('/^[a-z ]+$/', $word)
                ? preg_match('/\b' . preg_quote($word, '/') . '\b/', $text) === 1
                : Str::contains(str_replace(' ', '', $text), $word);
            if ($found) {
                return true;
            }
        }

        return false;
    }

    /**
     * 文章清單（標題、步驟摘要、網址）。
     *
     * @param  Collection<int, KnowledgeBase>  $articles
     * @return list<array{title: string, text: string, url: string}>
     */
    private function articleItems(Collection $articles): array
    {
        return $articles->map(fn (KnowledgeBase $article) => [
            'title' => $article->title,
            'text' => Str::limit($article->solution, 160),
            'url' => route('knowledge-base.show', $article, false),
        ])->values()->all();
    }

    /**
     * 「我要報修」按鈕：沒有報修權限的人不顯示；看過文章的話帶上文章編號。
     *
     * @return list<array{label: string, url: string}>
     */
    private function repairAction(User $user, ?int $articleId = null): array
    {
        return $user->can('repairs.create')
            ? [['label' => __('assistant.action.repair'), 'url' => route('repairs.create', $articleId ? ['from_kb' => $articleId] : [], false)]]
            : [];
    }

    /** 組成固定格式的回覆。 */
    private function reply(string $text, array $items = [], array $actions = [], array $suggestions = []): array
    {
        return compact('text', 'items', 'actions', 'suggestions');
    }
}
