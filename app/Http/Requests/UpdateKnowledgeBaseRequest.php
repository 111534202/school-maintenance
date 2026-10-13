<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;           // 驗證規則的型別（只用於下面的型別說明）
use Illuminate\Foundation\Http\FormRequest;                   // 「表單請求」父類別：把驗證規則獨立成一支類別

/**
 * 「編輯知識庫項目」表單的驗證規則，欄位跟新增時完全一樣。
 * （改規則時，記得 StoreKnowledgeBaseRequest 也要一起改，兩邊要保持一致。）
 */
class UpdateKnowledgeBaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * 這個人有沒有資格送這張表單：權限已由路由的 can:knowledge-base.manage 把關，所以這裡一律放行。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],     // 標題：必填
            'category' => ['nullable', 'string', 'max:100'],  // 分類：可不填
            'symptom' => ['required', 'string'],              // 常見故障現象：必填
            'solution' => ['required', 'string'],             // 自助排除步驟：必填
            'is_published' => ['required', 'boolean'],        // 是否上架：true / false
        ];
    }
}
