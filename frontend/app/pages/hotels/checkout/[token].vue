<!--
  Dedicated checkout page for the "Sessions - Components" flow.

  Routed to when the booking POST returns a `components_sdk_key` (Components
  mode). Mounts the Xendit Components SDK inline so the customer never leaves
  PM One. Layout is deliberately stripped of everything that distracts from
  completing a payment: no gallery, no room detail, no transfer breakdown, no
  cancellation policy, no documents block. The right rail keeps a compact
  order summary visible while the left rail owns the payment surface.

  Defensive routing:
  - status != pending_payment       → /hotels/reservation/{token}
  - has payment_url, no SDK key     → window.location to payment_url (legacy)
  - has neither                     → /hotels/reservation/{token} (let user retry)
-->
<template>
  <div class="mx-auto max-w-5xl space-y-6 px-4 pt-4 pb-16">
    <!-- Loading state -->
    <div v-if="pending && !reservation" class="space-y-6">
      <Skeleton class="h-6 w-32" />
      <div class="space-y-2">
        <Skeleton class="h-8 w-64" />
        <Skeleton class="h-4 w-40" />
      </div>
      <div class="grid gap-6 lg:grid-cols-12">
        <Skeleton class="h-96 lg:col-span-7" />
        <Skeleton class="h-72 lg:col-span-5" />
      </div>
    </div>

    <!-- Reservation not found -->
    <div
      v-else-if="!reservation"
      class="text-muted-foreground rounded-md border border-dashed py-12 text-center text-sm tracking-tight"
    >
      Reservation not found.
    </div>

    <template v-else>
      <!-- Header: back + help -->
      <div class="flex items-center justify-between gap-2">
        <ButtonBack :destination="`/hotels/reservation/${token}`" />
        <a
          :href="`mailto:${supportEmail}`"
          class="text-muted-foreground hover:text-foreground inline-flex items-center gap-x-1.5 text-sm tracking-tight transition-colors"
        >
          <Icon name="hugeicons:customer-support" class="size-4 shrink-0" />
          Need help?
        </a>
      </div>

      <!-- Title + urgency -->
      <div class="space-y-2">
        <h1 class="page-title">Complete payment</h1>
        <div
          class="text-muted-foreground flex flex-wrap items-center gap-x-2 gap-y-1 text-sm tracking-tight"
        >
          <span>
            Booking
            <code class="bg-muted/60 text-foreground rounded px-1.5 py-0.5 font-mono text-xs tracking-tight sm:text-sm">
              {{ reservation.reservation_number }}
            </code>
          </span>
          <span aria-hidden="true">·</span>
          <span v-if="expiryCountdown" :class="urgencyClass">
            <Icon name="hugeicons:clock-02" class="mr-1 inline size-3.5 shrink-0 align-text-bottom" />
            Expires in {{ expiryCountdown }}
          </span>
          <span v-else-if="expiresAtLabel">
            Expires on {{ expiresAtLabel }}
          </span>
        </div>
      </div>

      <!-- Mobile-only summary (collapsed accordion above payment) -->
      <details
        class="bg-muted/50 border-border group rounded-xl border lg:hidden"
        :open="false"
      >
        <summary
          class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 text-sm tracking-tight"
        >
          <span class="flex items-center gap-x-2">
            <Icon name="hugeicons:bill" class="text-muted-foreground size-4 shrink-0" />
            <span class="font-medium">Order summary</span>
          </span>
          <span class="text-foreground font-semibold tabular-nums tracking-tight">
            Rp{{ formatRupiah(reservation.amounts.total) }}
          </span>
        </summary>
        <div class="bg-background -m-px rounded-xl border px-4 py-5">
          <HotelsOrderSummary :reservation="reservation" />
        </div>
      </details>

      <!-- Main grid: payment (left) + summary (right, sticky) -->
      <div class="grid gap-6 lg:grid-cols-12">
        <!-- Payment column -->
        <div class="space-y-4 lg:col-span-7">
          <div class="frame">
            <div class="frame-header">
              <div class="frame-title">Payment method</div>
            </div>
            <div class="frame-panel">
              <template v-if="reservation.components_sdk_key">
                <ClientOnly>
                  <HotelsComponentsCheckout
                    :components-sdk-key="reservation.components_sdk_key"
                    :reservation-number="reservation.reservation_number"
                    :magic-link-token="token"
                  />
                  <template #fallback>
                    <div class="flex items-center justify-center gap-x-2 py-8">
                      <Spinner class="size-4 shrink-0" />
                      <span class="text-muted-foreground text-sm tracking-tight">
                        Loading payment options...
                      </span>
                    </div>
                  </template>
                </ClientOnly>
              </template>
              <template v-else>
                <!-- Defensive: should be redirected by mount guard, but keep
                     a fallback message in case the route is bookmarked. -->
                <div class="text-muted-foreground py-6 text-center text-sm tracking-tight">
                  Payment session unavailable. Redirecting...
                </div>
              </template>
            </div>
          </div>

          <!-- Trust strip -->
          <div
            class="text-muted-foreground flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-xs tracking-tight sm:text-sm"
          >
            <Icon name="hugeicons:shield-01" class="size-4 shrink-0" />
            <span>Secured by Xendit. Encrypted end-to-end.</span>
          </div>
        </div>

        <!-- Summary column (sticky on lg+) -->
        <aside class="hidden lg:col-span-5 lg:block">
          <div class="lg:sticky lg:top-20">
            <div class="frame">
              <div class="frame-header">
                <div class="frame-title">Order summary</div>
              </div>
              <div class="frame-panel">
                <HotelsOrderSummary :reservation="reservation" />
              </div>
            </div>
          </div>
        </aside>
      </div>
    </template>
  </div>
</template>

<script setup>
import { Skeleton } from "../../../components/ui/skeleton";
import { Spinner } from "../../../components/ui/spinner";

definePageMeta({
  layout: "default",
});

const route = useRoute();
const token = computed(() => route.params.token);

const { data, pending } = await useLazyAsyncData(
  () => `checkout-${token.value}`,
  () => $fetch(`/api/hotels/reservation/${token.value}`)
);

const reservation = computed(() => data.value?.data);

usePageMeta(null, {
  title: computed(() =>
    reservation.value?.reservation_number
      ? `Pay · ${reservation.value.reservation_number}`
      : "Complete payment"
  ),
});

// Defensive routing — checkout page is ONLY for active Components sessions.
// Anything else gets routed back to the reservation page or external link.
watchEffect(() => {
  const r = reservation.value;
  if (!r) return;
  if (r.status !== "pending_payment") {
    navigateTo(`/hotels/reservation/${token.value}`, { replace: true });
    return;
  }
  if (!r.components_sdk_key) {
    if (r.payment_url && import.meta.client) {
      window.location.href = r.payment_url;
    } else {
      navigateTo(`/hotels/reservation/${token.value}`, { replace: true });
    }
  }
});

const supportEmail = computed(
  () => reservation.value?.hotel?.contact_email || "support@pmone.id"
);

const expiresAtLabel = computed(() => {
  const iso = reservation.value?.payment_expires_at;
  if (!iso) return null;
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return null;
  return d.toLocaleString("en-GB", {
    day: "numeric",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  });
});

// Live countdown — refreshes every second client-side, falls back to the
// formatted timestamp when the difference would render as a >24h countdown
// (a countdown that big stops feeling urgent and starts feeling like noise).
const now = ref(Date.now());
let timer = null;
onMounted(() => {
  now.value = Date.now();
  timer = setInterval(() => {
    now.value = Date.now();
  }, 1000);
});
onBeforeUnmount(() => {
  if (timer) clearInterval(timer);
});

const expiryCountdown = computed(() => {
  const iso = reservation.value?.payment_expires_at;
  if (!iso) return null;
  const expiry = new Date(iso).getTime();
  if (Number.isNaN(expiry)) return null;
  const diff = expiry - now.value;
  if (diff <= 0) return "0:00";
  const totalSeconds = Math.floor(diff / 1000);
  if (totalSeconds > 86400) return null; // > 24h → use formatted label instead
  const h = Math.floor(totalSeconds / 3600);
  const m = Math.floor((totalSeconds % 3600) / 60);
  const s = totalSeconds % 60;
  if (h > 0) {
    return `${h}h ${String(m).padStart(2, "0")}m`;
  }
  return `${m}:${String(s).padStart(2, "0")}`;
});

const urgencyClass = computed(() => {
  const iso = reservation.value?.payment_expires_at;
  if (!iso) return "";
  const diff = new Date(iso).getTime() - now.value;
  if (diff <= 5 * 60 * 1000) return "text-destructive font-medium";
  if (diff <= 15 * 60 * 1000) return "text-warning-foreground font-medium";
  return "";
});

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);
</script>
