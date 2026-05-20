<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class RequestCommunityJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
