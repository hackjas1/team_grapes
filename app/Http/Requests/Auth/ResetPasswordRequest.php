<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['nullable', 'string'],
            'login' => ['nullable', 'string'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            if (empty($this->input('email')) && empty($this->input('login'))) {
                $v->errors()->add('email', 'Please provide your institutional email address or student ID.');
            }
        });
    }
}
