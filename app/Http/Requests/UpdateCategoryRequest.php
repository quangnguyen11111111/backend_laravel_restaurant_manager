<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateCategoryRequest extends BaseApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:256'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', 'in:' . implode(',', Category::STATUS_VALUES)],
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.max' => 'Tên danh mục không được quá 256 ký tự',
            'parent_id.integer' => 'ID danh mục cha phải là số nguyên',
            'parent_id.exists' => 'Danh mục cha không tồn tại',
            'status.in' => 'Trạng thái danh mục không hợp lệ',
            'order.integer' => 'Thứ tự phải là số nguyên',
            'order.min' => 'Thứ tự phải lớn hơn hoặc bằng 0',
        ];
    }
}
