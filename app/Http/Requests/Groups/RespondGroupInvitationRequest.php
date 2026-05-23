<?php

namespace App\Http\Requests\Groups;

use App\Models\Groups\Group;
use App\Models\Groups\GroupInvitation;
use Illuminate\Foundation\Http\FormRequest;

class RespondGroupInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');
        $invitation = $this->route('invitation');

        return $group instanceof Group
            && $invitation instanceof GroupInvitation
            && (int) $invitation->group_id === (int) $group->getKey()
            && (int) $invitation->invited_user_id === (int) ($this->user()?->getKey() ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
