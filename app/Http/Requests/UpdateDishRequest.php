<?php

namespace App\Http\Requests;

class UpdateDishRequest extends CreateDishRequest
{
    // muốn phần cập nhật thì không bắt buộc có ảnh thì phải có s3key
    public function rules(): array
    {
        $rules = parent::rules();

        // Khi cập nhật, ảnh không bắt buộc, nhưng nếu có ảnh thì phải có imageS3Key
        $rules['imageS3Key'] = ['nullable', 'string', 'max:512'];

        // Name, price, description, image không bắt buộc khi cập nhật
        $rules['name'] = ['nullable', 'string', 'min:1', 'max:256'];
        $rules['price'] = ['nullable', 'integer', 'min:1'];
        $rules['description'] = ['nullable', 'string', 'max:10000'];
        $rules['image'] = ['nullable', 'url'];

        return $rules;
    }
}
