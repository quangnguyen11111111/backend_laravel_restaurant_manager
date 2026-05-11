<?php

namespace App\Http\Requests;

class GuestCreateOrdersRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            '*.dishId' => ['required', 'integer'],
            '*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.dishId.required' => 'dishId là bắt buộc',
            '*.dishId.integer' => 'dishId phải là số nguyên',
            '*.quantity.required' => 'quantity là bắt buộc',
            '*.quantity.integer' => 'quantity phải là số nguyên',
            '*.quantity.min' => 'quantity phải lớn hơn hoặc bằng 1',
        ];
    }
}
