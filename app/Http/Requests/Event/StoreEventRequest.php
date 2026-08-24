<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'session_type' => ['nullable', 'string', 'in:half_day,whole_day'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'checkin_start_time' => ['nullable', 'date'],
            'checkin_end_time' => ['nullable', 'date'],
            'checkout_start_time' => ['nullable', 'date'],
            'checkout_end_time' => ['nullable', 'date'],
            'am_checkin_start_time' => ['nullable', 'date'],
            'am_checkin_end_time' => ['nullable', 'date'],
            'am_checkout_start_time' => ['nullable', 'date'],
            'am_checkout_end_time' => ['nullable', 'date'],
            'pm_checkin_start_time' => ['nullable', 'date'],
            'pm_checkin_end_time' => ['nullable', 'date'],
            'pm_checkout_start_time' => ['nullable', 'date'],
            'pm_checkout_end_time' => ['nullable', 'date'],
            'allow_window_bypass' => ['nullable', 'boolean'],
            'target_year_levels' => ['nullable', 'array'],
            'target_year_levels.*' => ['string'],
            'venue_name' => ['required', 'string', 'max:255'],
            'venue_latitude' => ['required', 'numeric', 'between:-90,90'],
            'venue_longitude' => ['required', 'numeric', 'between:-180,180'],
            'allowed_radius_meters' => ['required', 'numeric', 'min:1', 'max:5000'],
            'fine_amount' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'fine_per_slot' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'status' => ['nullable', 'string', 'in:upcoming,active,completed,cancelled'],
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['exists:users,id'],
        ];
    }
}
