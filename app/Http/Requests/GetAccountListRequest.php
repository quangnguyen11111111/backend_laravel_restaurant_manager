<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class GetAccountListRequest extends BaseApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'page.integer' => 'Trang phải là số nguyên',
            'page.min' => 'Trang phải lớn hơn hoặc bằng 1',
        ];
    }
}
