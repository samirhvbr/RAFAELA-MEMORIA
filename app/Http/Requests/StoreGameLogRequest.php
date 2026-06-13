<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGameLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'level' => ['required', 'integer', 'min:1', 'max:7'],
            'grid' => ['required', 'string', 'max:10'],
            'time_seconds' => ['required', 'integer', 'min:0', 'max:65535'],
            'moves' => ['required', 'integer', 'min:0', 'max:65535'],
            'errors' => ['required', 'integer', 'min:0', 'max:65535'],
            'hits' => ['required', 'integer', 'min:0', 'max:65535'],
            'score' => ['required', 'string', 'max:5', 'in:S,A+,A,B,C'],
            'status' => ['required', 'string', 'in:completed,abandoned'],
            'session_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
