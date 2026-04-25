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

        return $rules;
    }
}
