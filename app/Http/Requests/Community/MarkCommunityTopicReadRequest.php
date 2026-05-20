<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class MarkCommunityTopicReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'topic_seq' => ['required', 'integer', 'min:0'],
        ];
    }
}
