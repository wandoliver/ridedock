<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Accounts\Services\ManageInvitations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class InvitationController extends Controller
{
    public function show(string $token, ManageInvitations $invitations): View
    {
        $invitation = $invitations->find($token);
        app()->setLocale($invitation->user->locale);

        return view('auth.invitation', ['invitation' => $invitation, 'token' => $token]);
    }

    public function accept(AcceptInvitationRequest $request, string $token, ManageInvitations $invitations): RedirectResponse
    {
        $user = $invitations->accept($token, $request->validated('password'));
        app()->setLocale($user->locale);

        return to_route('login')->with('status', __('accounts.activated'));
    }
}
