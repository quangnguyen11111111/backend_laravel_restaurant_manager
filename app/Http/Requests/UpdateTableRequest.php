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
            'max_capacity' => ['nullable', 'integer', 'min:1'],
            'group_id' => ['nullable', 'string', 'max:255'],
            'group_order' => ['nullable', 'integer', 'min:1'],
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
            'max_capacity.integer' => 'Sức chứa tối đa phải là số nguyên',
            'max_capacity.min' => 'Sức chứa tối đa phải lớn hơn 0',
            'group_id.string' => 'Nhóm bàn phải là chuỗi',
            'group_id.max' => 'Nhóm bàn không được vượt quá 255 ký tự',
            'group_order.integer' => 'Thứ tự trong nhóm phải là số nguyên',
            'group_order.min' => 'Thứ tự trong nhóm phải lớn hơn 0',
            'status.in' => 'Trạng thái bàn không hợp lệ',
        ];
    }
}
