<?php

namespace App\Http\Requests\Admin;

use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        $userId = $this->route('id') ?: ($this->route('user') ? (is_object($this->route('user')) ? $this->route('user')->id : $this->route('user')) : null);

        return [
            'student_number' => ['nullable', 'string', 'max:50', Rule::unique('users', 'student_number')->ignore($userId)],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'year_level' => ['nullable', 'string', 'max:50'],
            'section_block' => ['nullable', 'string', 'max:50'],
            'role' => ['sometimes', 'required', Rule::in(['admin', 'event_staff', 'student'])],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'pending_onboarding'])],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Another user account with this email address already exists. Please use a unique email.',
            'student_number.unique' => 'Another user account with this Student / Staff ID number already exists.',
            'email.email' => 'Please enter a valid email address.',
        ];
    }
}
