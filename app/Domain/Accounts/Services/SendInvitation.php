<?php

namespace App\Domain\Accounts\Services;

use App\Mail\UserInvitationMail;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SendInvitation
{
    public function isAvailable(): bool
    {
        $transport = config('mail.mailers.'.config('mail.default').'.transport');

        return in_array($transport, ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'resend', 'mailgun'], true)
            || (app()->environment('testing') && $transport === 'array');
    }

    public function send(UserInvitation $invitation, string $url, User $actor): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        try {
            // Synchronous delivery keeps the raw link out of queues and failed-job payloads.
            Mail::to($invitation->email)->locale($invitation->user->locale)->send(new UserInvitationMail($url));
        } catch (Throwable) {
            // Transport exceptions can contain the raw message/link. Never log them.
            AccountAudit::record('invitation.email_failed', $invitation->user, $actor);
            return false;
        }

        DB::transaction(function () use ($invitation, $actor): void {
            $invitation->emailed_at = now();
            $invitation->save();
            AccountAudit::record('invitation.emailed', $invitation->user, $actor);
        });

        return true;
    }
}
