<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['nullable', 'string'],
            'login' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            if (empty($this->input('email')) && empty($this->input('login'))) {
                $v->errors()->add('login', 'Please provide your institutional email address or student ID.');
            }
        });
    }
}
