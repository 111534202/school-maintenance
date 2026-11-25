<?php

namespace App\Services\Assistant;

use App\Models\User;   // 用戶資料表模型

/**
 * 「AI 客服」的共用規格（介面）。目前的實作是 KnowledgeBaseAssistant（依知識庫自動回答，不連外部網路）。
 * 之後想換成真正的生成式 AI：另外寫一個實作這個介面的類別，再到 AppServiceProvider 改綁定那一行即可，
 * 畫面與 Controller 都不用動。
 */
interface AssistantInterface
{
    /**
     * 回答使用者的一句話。
     *
     * text = 回覆文字；items = 找到的文章或報修單；actions = 可以點的按鈕；suggestions = 建議再問的問題。
     *
     * @return array{
     *     text: string,
     *     items: list<array{title: string, text: string, url: string}>,
     *     actions: list<array{label: string, url: string}>,
     *     suggestions: list<string>
     * }
     */
    public function answer(User $user, string $question): array;
}
