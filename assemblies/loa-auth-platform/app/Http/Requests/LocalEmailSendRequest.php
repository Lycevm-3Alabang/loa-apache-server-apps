<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class LocalEmailSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['to', 'cc', 'bcc'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => [$this->input($key)]]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['email:rfc'],
            'cc' => ['sometimes', 'array'],
            'cc.*' => ['email:rfc'],
            'bcc' => ['sometimes', 'array'],
            'bcc.*' => ['email:rfc'],
            'subject' => ['required', 'string', 'max:255'],
            'html' => ['required', 'string', 'max:204800'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'ok' => false,
            'error' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
