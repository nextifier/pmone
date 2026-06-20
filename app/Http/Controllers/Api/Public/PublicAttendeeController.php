<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTicket\PersonalizeAttendeeRequest;
use App\Http\Resources\AttendeeResource;
use App\Models\Attendee;
use App\Models\MagicLink;
use App\Models\User;
use BaconQrCode\Renderer\Image\ImagickImageBackend;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PublicAttendeeController extends Controller
{
    /**
     * E-ticket for a single attendee. The opaque ulid in the URL is the access
     * key, so the page can be shared without a login (token = access).
     */
    public function show(string $ulid): JsonResponse
    {
        $attendee = Attendee::query()
            ->where('ulid', $ulid)
            ->with([
                'ticket',
                'ticketOrderItem.ticketOrder.event',
                'ticketOrderItem.selectedEventDay',
                'ticketOrderItem.ticketSession',
            ])
            ->firstOrFail();

        $order = $attendee->ticketOrderItem?->ticketOrder;

        return response()->json([
            'data' => (new AttendeeResource($attendee))->additional([])->resolve(),
            'order' => $order ? [
                'order_number' => $order->order_number,
                'status' => $order->status?->value,
                'is_confirmed' => $order->isConfirmed(),
            ] : null,
            'event' => $order?->event ? [
                'slug' => $order->event->slug,
                'title' => $order->event->title,
                'timezone' => $order->event->timezone,
                'login_button_enabled' => (bool) ($order->event->settings['tickets']['login_button_enabled'] ?? true),
            ] : null,
        ]);
    }

    /**
     * QR code image for the attendee, generated on the fly and streamed as PNG -
     * never written to disk and never attached to the email. The blade embeds it
     * as a plain <img src>, which is the most email-client-compatible way to show
     * a QR inline. The opaque ulid is the access key; the encoded value is the
     * attendee's qr_token (the same string the gate scanner reads), so a badge
     * scanned from the email behaves identically to one from the e-ticket page.
     * PNG bytes are cached in the cache store (keyed by qr_token, so a reissue
     * that rotates the token busts it automatically) to stay cheap under load.
     */
    public function qrImage(string $ulid): Response
    {
        $attendee = Attendee::query()->where('ulid', $ulid)->firstOrFail();

        $png = Cache::remember(
            "attendee-qr:{$attendee->qr_token}",
            now()->addDays(7),
            function () use ($attendee): string {
                $renderer = new ImageRenderer(
                    new RendererStyle(320, 1),
                    new ImagickImageBackend,
                );

                return (new Writer($renderer))->writeString($attendee->qr_token);
            },
        );

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * One-click dashboard sign-in for the ticket holder. Only works once the
     * attendee is linked to an account (they added their email). Reuses the
     * existing magic-link login: mint a short-lived single-use token (no email
     * sent) and return its verify URL, so the holder lands logged in without a
     * password. The ulid is the bearer capability - same trade-off as the page.
     */
    public function dashboardLink(Request $request, string $ulid): JsonResponse
    {
        $key = 'ticket-dashboard-link:'.$ulid;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many attempts. Please wait a moment and try again.',
            ], 429);
        }
        RateLimiter::hit($key, 60);

        $attendee = Attendee::query()->where('ulid', $ulid)->firstOrFail();

        // Require the email login token: an HMAC carried ONLY by the e-ticket
        // email's "Go to dashboard" button. The shareable e-ticket page URL never
        // contains it, so a bystander who has only that URL cannot mint a session
        // - even if the ticket was bought with someone else's email.
        $expected = $attendee->dashboardLoginToken();
        if (! $expected || ! hash_equals($expected, (string) $request->input('token'))) {
            return response()->json([
                'message' => 'This sign-in link is invalid. Use the "Go to dashboard" button in your ticket email.',
                'error_code' => 'INVALID_LOGIN_TOKEN',
            ], 403);
        }

        // Resolve the holder by the attendee's email (which the email itself
        // proves they own), so a returning visitor with an existing account can
        // sign in too - not only first-time buyers. Elevated/inactive accounts
        // never get a one-click session.
        $user = $attendee->resolveLoginableUser();
        if (! $user) {
            return response()->json([
                'message' => 'This ticket is not linked to a personal account.',
                'error_code' => 'NO_ACCOUNT',
            ], 422);
        }

        // Per-user cap in addition to the per-ulid limit above, so an attacker
        // who knows several of a user's ticket ulids cannot mint a flood of links.
        $userKey = 'ticket-dashboard-link-user:'.$user->id;
        if (RateLimiter::tooManyAttempts($userKey, 10)) {
            return response()->json([
                'message' => 'Too many attempts. Please wait a moment and try again.',
            ], 429);
        }
        RateLimiter::hit($userKey, 60);

        $magicLink = MagicLink::generate($user->email, 10);

        return response()->json([
            'url' => route('magic-link.verify', [
                'token' => $magicLink->token,
                'redirect' => '/account/tickets',
            ]),
        ]);
    }

    /**
     * Personalize an attendee (rename, optional email/phone). Providing an email
     * claims the ticket to that user account (created lazily if needed). Locked
     * once checked in - only staff may edit then.
     */
    public function personalize(PersonalizeAttendeeRequest $request, string $ulid): JsonResponse
    {
        $attendee = Attendee::query()->where('ulid', $ulid)->firstOrFail();

        if ($attendee->checked_in_at !== null) {
            return response()->json([
                'message' => 'This ticket has already been checked in and can no longer be edited here.',
            ], 422);
        }

        $validated = $request->validated();
        $attendee->name = $validated['name'];
        $attendee->phone = $validated['phone'] ?? $attendee->phone;
        $attendee->personalized_at = now();

        if (! empty($validated['email'])) {
            $attendee->email = $validated['email'];

            // Auto-link an account ONLY for a brand-new email (creates a fresh
            // visitor account, so one-click sign-in stays instant). An email that
            // already belongs to an account is never auto-claimed from a shareable
            // link - the real owner must sign in to link it. This prevents anyone
            // with the e-ticket URL from claiming someone else's (or a staff)
            // account by typing their email.
            if (! $attendee->claimed_by_user_id
                && ! User::query()->where('email', $validated['email'])->exists()) {
                $attendee->claimed_by_user_id = $this->resolveHolderUser($validated)->id;
            }
        }

        $attendee->save();

        return response()->json([
            'message' => 'Ticket personalized.',
            'data' => new AttendeeResource($attendee->fresh('ticket')),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveHolderUser(array $data): User
    {
        $email = trim((string) $data['email']);

        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $data['name'] ?? Str::before($email, '@'),
                'phone' => $data['phone'] ?? null,
                'username' => $this->uniqueUsername($email),
                'status' => 'active',
                'visibility' => 'private',
            ],
        );
    }

    protected function uniqueUsername(string $email): string
    {
        $base = Str::slug(Str::before($email, '@')) ?: 'visitor';
        $candidate = $base;
        $i = 1;
        while (User::query()->where('username', $candidate)->exists()) {
            $candidate = $base.'-'.$i++;
        }

        return $candidate;
    }
}
