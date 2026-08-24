<?php

namespace App\Http\Requests\Admin;

use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;

class ProvisionStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        $domain = SystemSetting::get('institutional_email_domain', 'tpc.edu.ph');

        return [
            'student_number' => ['required', 'string', 'max:50', 'unique:users,student_number'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                'unique:users,email',
            ],
            'role' => ['nullable', 'string', 'in:student,event_staff,admin'],
            'year_level' => ['nullable', 'string', 'max:50'],
            'section_block' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'A user account with this email address already exists. Please use a unique email address.',
            'student_number.unique' => 'A user account with this Student / Staff ID number already exists. Please check the ID number.',
            'email.required' => 'Email address is required for sending account activation details.',
            'email.email' => 'Please enter a valid email address.',
            'student_number.required' => 'Student / Staff ID number is required.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
        ];
    }
}
