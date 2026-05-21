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

    protected function prepareForValidation(): void
    {
        if ($this->has('body') && is_string($this->input('body'))) {
            $this->merge(['body' => trim($this->input('body'))]);
        }
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000', 'required_without:ciphertext'],
            'ciphertext' => ['nullable', 'string', 'required_with:nonce', 'required_without:body'],
            'nonce' => ['nullable', 'string', 'required_with:ciphertext', 'required_without:body'],
            'epoch_id' => ['nullable', 'uuid', 'exists:community_key_epochs,id'],
            'ttl_seconds' => ['nullable', 'integer', 'min:1'],
            'client_idempotency_key' => ['nullable', 'string', 'max:100'],
            'visibility' => ['nullable', Rule::in([CommunityPost::VISIBILITY_PUBLIC, CommunityPost::VISIBILITY_MEMBERS_ONLY, CommunityPost::VISIBILITY_PRIVATE])],
        ];
    }
}
