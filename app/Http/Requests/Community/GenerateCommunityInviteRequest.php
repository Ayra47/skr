<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class GenerateCommunityInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
