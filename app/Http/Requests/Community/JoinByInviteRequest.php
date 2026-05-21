<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class JoinByInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }
}
