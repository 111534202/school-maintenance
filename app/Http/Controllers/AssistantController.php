<?php

namespace App\Http\Controllers;

use App\Services\Assistant\AssistantInterface;   // AI 客服的共用規格
use Illuminate\Http\JsonResponse;                // JSON 回應
use Illuminate\Http\Request;                     // 這次的請求

/** 右下角「AI 客服」：收一句話、回一段 JSON（畫面在 layouts/partials/assistant-widget.blade.php）。 */
class AssistantController extends Controller
{
    /** 回答一個問題（POST /assistant/ask）。所有登入者都能用；回答內容依登入者的權限與資料範圍。 */
    public function ask(Request $request, AssistantInterface $assistant): JsonResponse
    {
        // 問題必填，最多 200 個字。
        $data = $request->validate(
            ['question' => ['required', 'string', 'max:200']],
            [],
            ['question' => __('assistant.ui.question')],
        );

        return response()->json($assistant->answer($request->user(), trim($data['question'])));
    }
}
