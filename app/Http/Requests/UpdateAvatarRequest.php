<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateAvatarRequest extends BaseApiRequest
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
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Ảnh đại diện là bắt buộc',
            'image.image' => 'File tải lên phải là hình ảnh',
            'image.mimes' => 'Ảnh chỉ hỗ trợ định dạng jpeg, jpg, png, webp',
            'image.max' => 'Kích thước ảnh tối đa là 2MB',
        ];
    }
}
