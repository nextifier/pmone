<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\Ticketing\TicketVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTicket\PreviewTicketCartRequest;
use App\Http\Resources\EventCustomFieldResource;
use App\Http\Resources\PublicTicketResource;
use App\Models\Event;
use App\Models\User;
use App\Services\Ticket\TicketPurchaseService;
use App\Support\PaymentChannels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rules\Email;

class PublicTicketController extends Controller
{
    public function __construct(protected TicketPurchaseService $purchases) {}

    /**
     * List the on-sale tickets (entry + add-on, first-party + external) for an
     * event website. The middleware already enforces tickets_enabled. Content is
     * localized via `?locale=`; `meta.terms` carries the staff-managed purchase
     * terms for this locale so the checkout can show them without an extra call.
     */
    public function index(Request $request, string $eventSlug): JsonResponse
    {
        $event = Event::query()->where('slug', $eventSlug)->firstOrFail();
        $locale = $this->applyLocale($request);

        // Hidden tickets are NEVER served through this (response-cached) listing —
        // they are revealed only by the uncached validate-access-code endpoint, so
        // a cached page can't leak one buyer's unlocked view to another (§8).
        // `code_required` tickets are listed but flagged `locked` in the resource.
        $tickets = $event->tickets()
            ->where('is_active', true)
            ->where('visibility', '!=', TicketVisibility::Hidden->value)
            ->with(['media', 'pricePhases', 'sessions', 'validDays'])
            ->orderBy('order_column')
            ->get();

        return response()->json([
            'data' => PublicTicketResource::collection($tickets),
            'meta' => [
                'terms' => $this->localizedTerms($event, $locale),
                'allowed_payment_channels' => $this->allowedChannelLogos($event),
            ],
        ]);
    }

    /**
     * Active business-matching fields for an event, so the checkout form can
     * render the conditional questions when the buyer opts in. Labels localized
     * via `?locale=`.
     */
    public function customFields(Request $request, string $eventSlug): JsonResponse
    {
        $event = Event::query()->where('slug', $eventSlug)->firstOrFail();
        $this->applyLocale($request);

        // No Business Matching program for this event -> no questions at checkout.
        $fields = $event->business_matching_enabled
            ? $event->eventCustomFields()->where('is_active', true)->orderBy('order_column')->get()
            : collect();

        return response()->json([
            'data' => EventCustomFieldResource::collection($fields),
        ]);
    }

    /**
     * Active ticket-registration fields for an event (predefined + custom),
     * answered per attendee: the buyer at checkout, other attendees via their
     * magic links. Labels localized via `?locale=`. Empty list when the event
     * has none, so websites that never configure this see no change.
     */
    public function registrationFields(Request $request, string $eventSlug): JsonResponse
    {
        $event = Event::query()->where('slug', $eventSlug)->firstOrFail();
        $this->applyLocale($request);

        $fields = $event->registrationFields()->where('is_active', true)->get();

        return response()->json([
            'data' => EventCustomFieldResource::collection($fields),
        ]);
    }

    /**
     * Resolve and apply the request locale (default: app locale) for translatable
     * resources, returning the resolved locale string.
     */
    protected function applyLocale(Request $request): string
    {
        $locale = (string) $request->input('locale', config('app.locale', 'en'));
        App::setLocale($locale);

        return $locale;
    }

    /**
     * Localize the staff-managed purchase terms stored as {locale: html} in
     * event.settings.tickets.terms (English fallback). Returns null when unset.
     */
    protected function localizedTerms(Event $event, string $locale): ?string
    {
        $terms = $event->settings['tickets']['terms'] ?? null;

        if (is_array($terms)) {
            return $terms[$locale] ?? $terms['en'] ?? null;
        }

        return is_string($terms) && $terms !== '' ? $terms : null;
    }

    /**
     * Payment-channel logos the event restricts ticket checkout to, for the
     * website to display only those at checkout. Empty = no restriction (the
     * site shows its default "we accept" set). Server-side enforcement still
     * happens at checkout creation, so this is purely informational.
     *
     * @return array<int, array{code: string, label: string, logo: string}>
     */
    protected function allowedChannelLogos(Event $event): array
    {
        $codes = $event->settings['tickets']['allowed_payment_channels'] ?? null;
        if (! is_array($codes) || $codes === []) {
            return [];
        }

        $byCode = collect(PaymentChannels::catalog())->keyBy('code');

        return collect($codes)
            ->map(fn ($code) => is_string($code) ? strtoupper($code) : null)
            ->filter()
            ->map(fn (string $code) => $byCode->get($code))
            ->filter()
            ->map(fn (array $entry) => [
                'code' => $entry['code'],
                'label' => $entry['label'],
                'logo' => $entry['logo_url'],
            ])
            ->values()
            ->all();
    }

    public function preview(PreviewTicketCartRequest $request): JsonResponse
    {
        $event = Event::findOrFail($request->integer('event_id'));

        $preview = $this->purchases->previewCart(
            $event,
            $request->input('items', []),
            $request->input('promo_code'),
            $request->input('email'),
            $request->input('access_code'),
            $request->input('phone'),
        );

        return response()->json(['data' => $preview]);
    }

    /**
     * Email-first lookup: returns ONLY whether an account exists, never any PII,
     * so the frontend can offer login before autofilling another person's data.
     */
    public function emailLookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', Email::default(), 'max:255'],
        ]);

        $exists = User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim($validated['email']))])->exists();

        return response()->json(['data' => ['exists' => $exists]]);
    }
}
