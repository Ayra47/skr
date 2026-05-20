<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class DeliverCommunityMemberKeysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keys' => ['required', 'array', 'min:1'],
            'keys.*.device_key_id' => ['required', 'uuid', 'exists:user_device_keys,id'],
            'keys.*.encrypted_key' => ['required', 'string'],
        ];
    }
}
