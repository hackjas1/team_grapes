<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class ManualOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && in_array($user->role, ['admin', 'event_staff']);
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'exists:events,id'],
            'student_id' => ['nullable', 'integer'],
            'student_identifier' => ['nullable', 'string'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'status' => ['nullable', 'string', 'in:present,late,manual_override'],
        ];
    }
}
