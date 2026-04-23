<?php

namespace App\Http\Requests;

use App\Models\Dish;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class CreateDishRequest extends BaseApiRequest
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
            'name' => ['required', 'string', 'min:1', 'max:256'],
            'price' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:10000'],
            'image' => ['required', 'url'],
             'imageS3Key' => ['nullable', 'string', 'max:512', 'required_with:image'],
            'status' => ['nullable', Rule::in(Dish::STATUS_VALUES)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên món ăn là bắt buộc',
            'name.max' => 'Tên món ăn không được vượt quá 256 ký tự',
            'price.required' => 'Giá món ăn là bắt buộc',
            'price.integer' => 'Giá món ăn phải là số nguyên',
            'price.min' => 'Giá món ăn phải lớn hơn 0',
            'description.required' => 'Mô tả món ăn là bắt buộc',
            'description.max' => 'Mô tả món ăn không được vượt quá 10000 ký tự',
            'image.required' => 'Hình ảnh món ăn là bắt buộc',
            'image.url' => 'Hình ảnh món ăn phải là URL hợp lệ',
            'imageS3Key.required_with' => 'Khóa ảnh S3 là bắt buộc khi gửi hình ảnh',
            'imageS3Key.string' => 'Khóa ảnh S3 không hợp lệ',
        ];
    }
}
