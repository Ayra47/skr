<?php

namespace App\Http\Requests\Community;

use App\Models\CommunityPost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublishCommunityPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['prohibited'],
            'ciphertext' => ['required', 'string'],
            'nonce' => ['required', 'string'],
            'epoch_id' => ['required', 'uuid', 'exists:community_key_epochs,id'],
            'ttl_seconds' => ['nullable', 'integer', 'min:1'],
            'client_idempotency_key' => ['nullable', 'string', 'max:100'],
            'visibility' => ['nullable', Rule::in([CommunityPost::VISIBILITY_PUBLIC, CommunityPost::VISIBILITY_MEMBERS_ONLY, CommunityPost::VISIBILITY_PRIVATE])],
        ];
    }
}
