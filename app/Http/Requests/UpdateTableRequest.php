<?php

namespace App\Http\Requests;

use App\Models\Table;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateTableRequest extends BaseApiRequest
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
            'changeToken' => ['required', 'boolean'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(Table::STATUS_VALUES)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'changeToken.required' => 'Trạng thái đổi token là bắt buộc',
            'changeToken.boolean' => 'Trạng thái đổi token không hợp lệ',
            'capacity.required' => 'Sức chứa là bắt buộc',
            'capacity.integer' => 'Sức chứa phải là số nguyên',
            'capacity.min' => 'Sức chứa phải lớn hơn 0',
            'status.in' => 'Trạng thái bàn không hợp lệ',
        ];
    }
}
