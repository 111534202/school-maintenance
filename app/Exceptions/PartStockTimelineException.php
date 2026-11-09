<?php

namespace App\Exceptions;

use DomainException;

/**
 * 依日期重新計算先進先出後，某一筆使用或報廢「在它的日期當時」庫存不夠扣。
 * 訊息固定以「錯誤，請重新確認：」開頭，畫面會用彈出視窗顯示（layouts/app）。
 */
class PartStockTimelineException extends DomainException
{
    public const PREFIX = '錯誤，請重新確認：';

    /**
     * 這次畫面要不要跳出「錯誤，請重新確認」視窗：從 session 的錯誤訊息或欄位錯誤裡找以 PREFIX 開頭的那一則，
     * 回傳去掉開頭後的內容；沒有就回傳 null。
     */
    public static function pendingMessage($errors = null): ?string
    {
        $messages = [session('error')];
        if ($errors instanceof \Illuminate\Support\ViewErrorBag || $errors instanceof \Illuminate\Support\MessageBag) {
            $messages = array_merge($messages, $errors->all());   // ViewErrorBag 的 all() 是轉給預設的錯誤袋
        }
        foreach ($messages as $message) {
            if (is_string($message) && str_starts_with($message, self::PREFIX)) {
                return mb_substr($message, mb_strlen(self::PREFIX));
            }
        }

        return null;
    }
}
