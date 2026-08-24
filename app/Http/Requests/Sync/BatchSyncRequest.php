<?php

namespace App\Http\Requests\Sync;

use Illuminate\Foundation\Http\FormRequest;

class BatchSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && in_array($user->role, ['admin', 'event_staff']);
    }

    public function rules(): array
    {
        return [
            'records' => ['required', 'array', 'min:1'],
            'records.*.local_record_id' => ['required', 'string', 'max:100'],
            'records.*.event_id' => ['required', 'integer'],
            'records.*.user_id' => ['required', 'integer'],
            'records.*.scan_time' => ['required', 'date'],
            'records.*.latitude' => ['nullable', 'numeric'],
            'records.*.longitude' => ['nullable', 'numeric'],
            'records.*.device_credential' => ['nullable', 'string'],
            'records.*.override_reason' => ['nullable', 'string'],
            'records.*.status' => ['nullable', 'string', 'in:present,late,manual_override'],
        ];
    }
}
