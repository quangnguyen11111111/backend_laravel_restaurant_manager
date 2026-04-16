<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseApiRequest extends FormRequest
{
    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = [];

        foreach ($validator->errors()->getMessages() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = [
                    'field' => (string) $field,
                    'message' => (string) $message,
                ];
            }
        }

        throw new HttpResponseException(response()->json([
            'message' => 'Dữ liệu không hợp lệ',
            'statusCode' => 422,
            'errors' => $errors,
        ], 422));
    }
}
