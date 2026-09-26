<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** 「編輯知識庫項目」表單的驗證規則，欄位跟新增時完全一樣。 */
class UpdateKnowledgeBaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * 尚未接上林政寬那邊的角色權限 Middleware（本週不要求），暫時一律放行。
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
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'symptom' => ['required', 'string'],
            'solution' => ['required', 'string'],
            'is_published' => ['required', 'boolean'],
        ];
    }
}
