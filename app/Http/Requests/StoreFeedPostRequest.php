<?php

namespace App\Http\Requests;

use App\Models\FeedPost;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreFeedPostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:2000', 'required_without:attachments'],
            'visibility' => ['required', Rule::in(FeedPost::visibilityValues())],
            'expires_in' => ['required', Rule::in(FeedPost::expirationValues())],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'nullable',
                'extensions:jpg,jpeg,png,gif,webp,mp4,webm,mov,pdf,txt,md,zip',
                File::types(['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov', 'pdf', 'txt', 'md', 'zip'])
                    ->max(500 * 1024),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge([
                'body' => trim($this->input('body')),
            ]);
        }

        if (! $this->has('expires_in')) {
            $this->merge([
                'expires_in' => FeedPost::EXPIRES_24H,
            ]);
        }
    }
}
