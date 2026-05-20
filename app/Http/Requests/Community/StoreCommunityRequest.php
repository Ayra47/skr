<?php

namespace App\Http\Requests\Community;

use App\Models\Community;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'alpha_dash', 'max:100', Rule::unique('communities', 'slug')],
            'description' => ['nullable', 'string', 'max:2000'],
            'visibility' => ['nullable', Rule::in([Community::VISIBILITY_PUBLIC, Community::VISIBILITY_PRIVATE, Community::VISIBILITY_HIDDEN])],
            'join_mode' => ['nullable', Rule::in([Community::JOIN_OPEN, Community::JOIN_INVITE_ONLY, Community::JOIN_REQUEST])],
            'member_limit' => ['nullable', 'integer', Rule::in(Community::ALLOWED_MEMBER_LIMITS)],
            'default_post_ttl_seconds' => ['nullable', 'integer', Rule::in(Community::ALLOWED_TTL_SECONDS)],
            'invite_policy' => ['nullable', Rule::in([Community::INVITE_POLICY_ALL_MEMBERS, Community::INVITE_POLICY_MODERATORS_ONLY])],
            'posting_policy' => ['nullable', Rule::in([Community::POSTING_POLICY_EVERYONE, Community::POSTING_POLICY_MODERATORS_ONLY])],
            'allow_posts_in_member_feed' => ['nullable', 'boolean'],
            'hide_real_names' => ['nullable', 'boolean'],
            'show_key_fingerprints' => ['nullable', 'boolean'],
            'anonymous_reactions_enabled' => ['nullable', 'boolean'],
        ];
    }
}
