<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class GetGuestListRequest extends BaseApiRequest
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
            'fromDate' => ['nullable', 'date'],
            'toDate' => ['nullable', 'date'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fromDate.date' => 'Ngày bắt đầu không hợp lệ',
            'toDate.date' => 'Ngày kết thúc không hợp lệ',
        ];
    }
}
