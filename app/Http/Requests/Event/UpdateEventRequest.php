<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) return false;

        if ($user->role === 'admin') return true;

        // Staff can update if assigned to the event
        if ($user->role === 'event_staff') {
            $routeParam = $this->route('id') ?: $this->route('event');
            $eventId = is_object($routeParam) ? $routeParam->id : $routeParam;
            return $eventId && ($user->assignedEvents()->where('event_id', $eventId)->exists() || \App\Models\Event::where('id', $eventId)->where('created_by', $user->id)->exists());
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'session_type' => ['nullable', 'string', 'in:half_day,whole_day'],
            'start_time' => ['sometimes', 'required', 'date'],
            'end_time' => ['sometimes', 'required', 'date'],
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
            'venue_name' => ['sometimes', 'required', 'string', 'max:255'],
            'venue_latitude' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'venue_longitude' => ['sometimes', 'required', 'numeric', 'between:-180,180'],
            'allowed_radius_meters' => ['sometimes', 'required', 'numeric', 'min:1', 'max:5000'],
            'fine_amount' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'fine_per_slot' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'status' => ['sometimes', 'required', 'string', 'in:upcoming,active,completed,cancelled'],
        ];
    }
}
