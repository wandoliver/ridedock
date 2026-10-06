<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use App\Domain\Accounts\Services\ManageInvitations;
use Illuminate\Validation\Rules\Password;

final class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $token = $this->route('token');
        abort_unless(is_string($token), 404);
        $invitation = app(ManageInvitations::class)->find($token);
        app()->setLocale($invitation->user->locale);

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['password' => ['required', 'string', 'confirmed', Password::min(12), 'max:255']];
    }
}
