<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class MarkCommunityReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'community_seq' => ['required', 'integer', 'min:0'],
        ];
    }
}
