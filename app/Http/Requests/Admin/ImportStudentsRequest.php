<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:5120'],
            'csv_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }
}
