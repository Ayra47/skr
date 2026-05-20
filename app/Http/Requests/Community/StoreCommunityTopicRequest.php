<?php

namespace App\Http\Requests\Community;

use App\Models\Community;
use App\Models\CommunityTopic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunityTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Community $community */
        $community = $this->route('community');

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'nullable',
                'alpha_dash',
                'max:100',
                Rule::unique('community_topics', 'slug')->where('community_id', $community->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'posting_policy' => ['nullable', Rule::in([CommunityTopic::POSTING_POLICY_EVERYONE, CommunityTopic::POSTING_POLICY_MODERATORS_ONLY])],
        ];
    }
}
