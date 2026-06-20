<?php

namespace App\Jobs\Ticket;

use App\Helpers\PhoneCountryHelper;
use App\Mail\Ticket\AccessCodeInviteMail;
use App\Models\AccessCode;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Delivers an access-code invitation: a magic invite link (auto-fills the code
 * on the event website) via email and/or WhatsApp. Best-effort — a failure on
 * one channel never blocks the other.
 */
class SendAccessCodeInviteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $accessCodeId) {}

    public function handle(): void
    {
        $code = AccessCode::query()
            ->with(['event.project.links', 'unlocks:id,slug,title'])
            ->find($this->accessCodeId);

        if (! $code) {
            return;
        }

        $inviteUrl = $this->buildInviteUrl($code);

        if ($code->bind_email) {
            Mail::to($code->bind_email)->send(new AccessCodeInviteMail($code, $inviteUrl));
        }

        if ($code->bind_phone && config('services.whatsapp.token')) {
            $this->sendWhatsApp($code, $inviteUrl);
        }
    }

    protected function buildInviteUrl(AccessCode $code): string
    {
        $base = $code->event?->publicBaseUrl() ?? rtrim((string) config('app.frontend_url'), '/');

        return "{$base}/tickets?invite=".rawurlencode($code->code);
    }

    protected function sendWhatsApp(AccessCode $code, string $inviteUrl): void
    {
        try {
            $eventTitle = $code->event?->title ?? 'the event';
            $template = (string) config('services.whatsapp.templates.access_code_invite', 'access_code_invite');

            app(WhatsAppService::class)->sendTemplate(
                PhoneCountryHelper::toWhatsAppNumber($code->bind_phone),
                $template,
                [$eventTitle, $code->code, $inviteUrl],
                'id',
            );
        } catch (\Throwable $e) {
            Log::warning('Access code WhatsApp invite failed', [
                'access_code_id' => $code->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
