<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Accounts\Services\ManageInvitations;
use App\Domain\Accounts\Services\SendInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UserController extends Controller
{
    public function index(SendInvitation $sender): View
    {
        Gate::authorize('manage-users');

        return $this->listing($sender);
    }

    public function store(CreateUserRequest $request, ManageInvitations $invitations, SendInvitation $sender): View|RedirectResponse
    {
        $delivery = $request->validated('delivery');
        $this->checkDelivery($delivery, $sender);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        [$invitation, $token] = $invitations->create($actor, [
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'locale' => $request->validated('locale'),
        ]);

        return $this->deliver($invitation, $token, $delivery, $actor, $sender);
    }

    public function reissue(Request $request, User $user, ManageInvitations $invitations, SendInvitation $sender): View|RedirectResponse
    {
        Gate::authorize('manage-users');
        $validated = $request->validate(['delivery' => ['required', Rule::in(['manual', 'email'])]]);
        $this->checkDelivery($validated['delivery'], $sender);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        [$invitation, $token] = $invitations->reissue($actor, $user);

        return $this->deliver($invitation, $token, $validated['delivery'], $actor, $sender);
    }

    public function revoke(Request $request, User $user, ManageInvitations $invitations): RedirectResponse
    {
        Gate::authorize('manage-users');
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $invitations->revoke($actor, $user);

        return to_route('admin.users.index')->with('status', __('accounts.revoked'));
    }

    public function quotas(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-users');
        $values = $request->validate([
            'ai_activity_quota' => ['present', 'nullable', 'integer', 'min:0', 'max:100000'],
            'ai_plan_quota' => ['present', 'nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $values, $request): void {
            \Illuminate\Support\Facades\DB::table('operation_mutexes')->where('name', 'ai')->lockForUpdate()->first();
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill($values)->save();
            \App\Domain\Accounts\Services\AccountAudit::record('ai_quotas_updated', $locked, $request->user());
        }, 3);
        return to_route('admin.users.index')->with('status', __('operations.quotas_saved'));
    }

    private function checkDelivery(string $delivery, SendInvitation $sender): void
    {
        if ($delivery === 'email' && ! $sender->isAvailable()) {
            throw ValidationException::withMessages(['delivery' => __('accounts.mail_unavailable')]);
        }
    }

    private function deliver(UserInvitation $invitation, string $token, string $delivery, User $actor, SendInvitation $sender): View|RedirectResponse
    {
        // Bind links to configured APP_URL, never the request's Host header.
        $url = rtrim((string) config('app.url'), '/').route('invitations.show', ['token' => $token], false);
        if ($delivery === 'email') {
            $sent = $sender->send($invitation, $url, $actor);
            return to_route('admin.users.index')->with($sent ? 'status' : 'error', __($sent ? 'accounts.sent' : 'accounts.mail_failed'));
        }

        // Render directly: do not flash the raw link to the session or store it in the database.
        return $this->listing($sender, $url);
    }

    private function listing(SendInvitation $sender, ?string $activationUrl = null): View
    {
        return view('admin.users', [
            'users' => User::query()->with('latestInvitation')->orderBy('name')->orderBy('id')->paginate(25),
            'mailAvailable' => $sender->isAvailable(),
            'activationUrl' => $activationUrl,
        ]);
    }
}
