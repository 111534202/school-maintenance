<?php

namespace App\Support;

use App\Models\Device;
use App\Models\Part;

/**
 * 設備編號與零件編號（可以自己填、會印在 QR Code／標籤上給掃描器讀的編號）的共用規則：
 * - 只能用英文字母、數字、「-」、「_」、「.」，最多 20 個字
 * - 設備編號和零件編號放在一起看都不能重複（大小寫視為相同；已停用的設備也算），
 *   這樣掃描到一個編號時一定只會對應到一台設備或一種零件。
 */
class EquipmentCode
{
    public const PATTERN = '/^[A-Za-z0-9._-]+$/';

    public const MAX = 20;

    /** 輸入框下面的規則說明，設備與零件共用。 */
    public const RULE_TEXT = '限英文字母、數字、「-」、「_」、「.」，最多 20 個字，不能和其他編號重複';

    /** 編號會出現在網址裡，「.」「..」會被瀏覽器改寫，不能當編號。 */
    public const RESERVED = ['.', '..'];

    /** 格式對不對（字元與長度）。 */
    public static function validFormat(string $code): bool
    {
        return (bool) preg_match(self::PATTERN, $code) && mb_strlen($code) <= self::MAX;
    }

    /** 格式錯誤時的說明，例如「設備編號只能用英文字母、數字、「-」、「_」、「.」，最多 20 個字。」 */
    public static function formatMessage(string $label): string
    {
        return "{$label}只能用英文字母、數字、「-」、「_」、「.」，最多 " . self::MAX . ' 個字。';
    }

    /**
     * 這個編號跟誰重複：零件的零件編號，或設備的設備編號。
     * 沒有重複回傳 null；有重複回傳完整的說明，例如：
     * 「HDMI-2」已經是零件「HDMI 線」的零件編號，請換一個。
     * 「DEV-0006」已經是設備「創客教室 印表機」的設備編號，請換一個。
     * 修改時傳自己的 id 排除自己（零件傳 $ignorePartId、設備傳 $ignoreDeviceId）。
     */
    public static function conflict(string $code, ?int $ignorePartId = null, ?int $ignoreDeviceId = null): ?string
    {
        $code = trim($code);
        $normalized = mb_strtolower($code);

        $part = Part::query()
            ->whereRaw('LOWER(part_code) = ?', [$normalized])
            ->when($ignorePartId, fn ($q) => $q->whereKeyNot($ignorePartId))
            ->first(['id', 'part_code', 'name']);
        if ($part) {
            return "「{$code}」已經是零件「{$part->name}」的零件編號，請換一個。";
        }

        $device = Device::withTrashed()
            ->with(['category:id,name', 'classroom:id,name'])
            ->whereRaw('LOWER(device_code) = ?', [$normalized])
            ->when($ignoreDeviceId, fn ($q) => $q->whereKeyNot($ignoreDeviceId))
            ->first();
        if ($device) {
            $label = trim(($device->classroom?->name ?? '') . ' ' . ($device->category?->name ?? ''));
            $label = $label === '' ? $device->device_code : $label;

            return "「{$code}」已經是設備「{$label}」的設備編號，請換一個。";
        }

        return null;
    }
}
