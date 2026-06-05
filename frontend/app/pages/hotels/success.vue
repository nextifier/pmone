<template>
  <div class="mx-auto max-w-3xl space-y-6 px-4 pt-4 pb-16">
    <!-- Loading skeleton while reservation summary is fetching -->
    <div v-if="pending && !reservation" class="space-y-6">
      <div class="space-y-4 pt-4 text-center">
        <Skeleton class="mx-auto size-14 rounded-full" />
        <div class="space-y-1.5">
          <Skeleton class="mx-auto h-8 w-56 sm:h-9" />
          <Skeleton class="mx-auto h-4 w-72" />
        </div>
        <Skeleton class="mx-auto h-9 w-40 rounded-md" />
      </div>
      <Skeleton class="h-44 w-full rounded-xl" />
      <Skeleton class="h-24 w-full rounded-xl" />
      <Skeleton class="h-44 w-full rounded-xl" />
    </div>

    <template v-else>
      <!-- Hero: status + reservation reference -->
      <header class="space-y-4 pt-4 text-center">
        <div class="flex justify-center">
          <span
            :class="[
              'inline-flex size-14 items-center justify-center rounded-full',
              heroIconBg,
            ]"
          >
            <Icon :name="heroIcon" :class="['size-7', heroIconColor]" />
          </span>
        </div>
        <div class="space-y-1.5">
          <h1 class="page-title">{{ heroTitle }}</h1>
          <p class="text-muted-foreground mx-auto max-w-md text-sm tracking-tight sm:text-base">
            {{ heroDescription }}
          </p>
        </div>
        <div v-if="bookingRef" class="flex items-center justify-center gap-x-2 pt-1">
          <code
            class="bg-muted/60 inline-flex items-center rounded px-2 py-1 font-mono text-sm tracking-tight sm:text-base"
          >
            {{ bookingRef }}
          </code>
          <ButtonCopy :text="String(bookingRef)" />
        </div>
      </header>

      <!-- Booking summary — only when full reservation (magic-link fetch).
           The status-by-number fallback returns just status + label, so the
           summary would render empty placeholders for hotel/check-in/total.
           Hide it entirely in that case; the success hero already shows the
           reservation number, status, and what's next. -->
      <div v-if="reservation && reservation.items" class="frame">
        <div class="frame-header">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="frame-title">Booking Summary</div>
            <Badge :variant="statusVariant" with-icon plain>
              {{ reservation.status_label }}
            </Badge>
          </div>
        </div>
        <div class="frame-panel space-y-4">
          <div class="space-y-1">
            <p class="text-sm font-medium tracking-tight">{{ reservation.hotel?.name }}</p>
            <p
              v-if="reservation.hotel?.city"
              class="text-muted-foreground text-xs tracking-tight sm:text-sm"
            >
              {{ reservation.hotel.city }}
            </p>
          </div>

          <div class="grid grid-cols-2 gap-3 text-sm tracking-tight sm:grid-cols-4">
            <div>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Check-in</p>
              <p class="font-medium">{{ formatShortDate(firstCheckIn) }}</p>
            </div>
            <div>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Check-out</p>
              <p class="font-medium">{{ formatShortDate(lastCheckOut) }}</p>
            </div>
            <div>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Nights</p>
              <p class="font-medium tabular-nums">{{ totalNights }}</p>
            </div>
            <div>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Rooms</p>
              <p class="font-medium tabular-nums">{{ totalRooms }}</p>
            </div>
          </div>

          <div class="flex items-baseline justify-between border-t pt-3">
            <span class="text-muted-foreground text-sm tracking-tight">
              {{ isPaid ? "Total paid" : "Total" }}
            </span>
            <span class="text-base font-semibold tabular-nums tracking-tight sm:text-lg">
              Rp{{ formatRupiah(reservation.amounts?.total ?? 0) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Documents — PDF endpoints are magic-token-only, so this section
           is irrelevant without a token. -->
      <div v-if="magicToken" class="frame">
        <div class="frame-header">
          <div class="frame-title">Documents</div>
        </div>
        <div class="frame-panel">
          <div class="flex flex-wrap items-center justify-center gap-2">
            <Button v-if="!isPaid" as-child variant="outline" size="sm">
              <a :href="invoicePdfUrl" target="_blank" rel="noopener">
                <Icon name="hugeicons:invoice-03" class="size-4 shrink-0" />
                Download Invoice
              </a>
            </Button>
            <Button v-if="isPaid" as-child variant="outline" size="sm">
              <a :href="receiptPdfUrl" target="_blank" rel="noopener">
                <Icon name="hugeicons:invoice-04" class="size-4 shrink-0" />
                Download Receipt
              </a>
            </Button>
          </div>
          <p
            v-if="!isPaid"
            class="text-muted-foreground mt-3 text-center text-xs tracking-tight sm:text-sm"
          >
            Receipt will be available once payment is confirmed.
          </p>
        </div>
      </div>

      <!-- What's next -->
      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">What's Next</div>
        </div>
        <div class="frame-panel">
          <ol class="space-y-3">
            <li
              v-for="(step, idx) in nextSteps"
              :key="idx"
              class="flex items-start gap-3 text-sm tracking-tight"
            >
              <span
                :class="[
                  'mt-0.5 inline-flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-medium tabular-nums tracking-tight',
                  step.done ? 'bg-success/15 text-success-foreground' : 'bg-muted text-muted-foreground',
                ]"
              >
                <Icon v-if="step.done" name="hugeicons:checkmark-circle-02" class="size-4 shrink-0" />
                <span v-else>{{ idx + 1 }}</span>
              </span>
              <div class="flex-1 space-y-0.5">
                <p :class="['font-medium', step.done ? 'text-foreground' : '']">{{ step.title }}</p>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  {{ step.description }}
                </p>
              </div>
            </li>
          </ol>
        </div>
      </div>

      <!-- Footer actions -->
      <div class="flex flex-wrap items-center justify-center gap-2">
        <Button v-if="magicToken" as-child>
          <NuxtLink :to="`/hotels/reservation/${magicToken}`">
            <Icon name="hugeicons:notebook-01" class="size-4 shrink-0" />
            View Full Details
          </NuxtLink>
        </Button>
        <Button as-child variant="outline">
          <NuxtLink to="/hotels">
            <Icon name="hugeicons:hotel-01" class="size-4 shrink-0" />
            Browse More Hotels
          </NuxtLink>
        </Button>
      </div>

      <p
        v-if="magicToken"
        class="text-muted-foreground text-center text-xs tracking-tight sm:text-sm"
      >
        Save the
        <NuxtLink :to="`/hotels/reservation/${magicToken}`" class="text-primary hover:underline">
          reservation details link
        </NuxtLink>
        to check your status anytime.
      </p>
    </template>
  </div>
</template>

<script setup>
import { Badge } from "../../components/ui/badge";
import { Button } from "../../components/ui/button";
import { Skeleton } from "../../components/ui/skeleton";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  layout: "default",
  noFooter: true,
});

const route = useRoute();
const bookingRef = computed(() => route.query.ref);
const magicToken = computed(() => route.query.token);

// Pull the reservation summary so the success page can show booking context,
// totals, and a Receipt download once Xendit's webhook lands. When the magic
// token is missing (Xendit sometimes redirects with `?ref=` only), fall back
// to a lightweight status lookup by reservation_number so step badges still
// reflect the real payment state instead of looking stuck at "incomplete".
const { data, pending, refresh } = await useLazyAsyncData(
  () => `reservation-success-${magicToken.value || bookingRef.value || "none"}`,
  async () => {
    if (magicToken.value) {
      const byToken = await $fetch(`/api/hotels/reservation/${magicToken.value}`).catch(
        () => null
      );
      if (byToken) return byToken;
    }
    // Fall back to a status lookup by reservation number so the page still
    // reflects the real payment state even if the magic token can't resolve.
    if (bookingRef.value) {
      return await $fetch(`/api/hotels/reservation/status/${bookingRef.value}`).catch(
        () => null
      );
    }
    return null;
  }
);

const reservation = computed(() => data.value?.data ?? null);

usePageMeta(null, {
  title: "Booking Successful · Hotels",
});

const isPaid = computed(() =>
  ["paid", "voucher_sent"].includes(reservation.value?.status)
);

const isPending = computed(() => reservation.value?.status === "pending_payment");

// Without a magic token the page can't fetch reservation status (Xendit may
// redirect with `ref` only). Treat that as the neutral "we received your
// booking" state — don't speculate "Payment successful" while the steps
// below still show step 1 as incomplete.
const isUnknownStatus = computed(() => !reservation.value);

// Right after an Xendit redirect the payment webhook may not have landed yet,
// so poll briefly so the page flips to "Payment successful" on its own.
const pollTimer = ref(null);
onMounted(() => {
  if (isPaid.value) return;
  let attempts = 0;
  pollTimer.value = setInterval(async () => {
    attempts += 1;
    await refresh();
    if (isPaid.value || attempts >= 10) {
      clearInterval(pollTimer.value);
      pollTimer.value = null;
    }
  }, 3000);
});
onBeforeUnmount(() => {
  if (pollTimer.value) {
    clearInterval(pollTimer.value);
  }
});

const heroIcon = computed(() => {
  if (isPaid.value) return "hugeicons:checkmark-circle-02";
  if (isPending.value || isUnknownStatus.value) return "hugeicons:clock-02";
  return "hugeicons:checkmark-circle-02";
});

const heroIconBg = computed(() =>
  isPaid.value ? "bg-success/15" : "bg-warning/15"
);

const heroIconColor = computed(() =>
  isPaid.value ? "text-success-foreground" : "text-warning-foreground"
);

const heroTitle = computed(() => {
  if (isPaid.value) return "Payment successful";
  return "Booking received";
});

const heroDescription = computed(() => {
  if (isPaid.value) {
    return "Thank you. Your payment has been received and your booking is confirmed.";
  }
  if (isPending.value) {
    return "We're still confirming your payment. This usually only takes a moment.";
  }
  return "Thank you. We've received your booking and will email a confirmation shortly.";
});

const statusVariant = computed(() => {
  const map = {
    pending_payment: "warning",
    paid: "success",
    voucher_sent: "success",
    expired: "muted",
    cancelled: "destructive",
    refunded: "destructive",
  };
  return map[reservation.value?.status] || "muted";
});

const firstCheckIn = computed(() => {
  const items = reservation.value?.items ?? [];
  if (!items.length) return null;
  return items
    .map((i) => i.check_in_date)
    .sort()
    .at(0);
});

const lastCheckOut = computed(() => {
  const items = reservation.value?.items ?? [];
  if (!items.length) return null;
  return items
    .map((i) => i.check_out_date)
    .sort()
    .at(-1);
});

const totalNights = computed(() => {
  const items = reservation.value?.items ?? [];
  return items.reduce((sum, i) => sum + Number(i.nights || 0), 0);
});

const totalRooms = computed(() => {
  const items = reservation.value?.items ?? [];
  return items.reduce((sum, i) => sum + Number(i.qty || 0), 0);
});

const invoicePdfUrl = computed(() => `/api/hotels/reservation/${magicToken.value}/invoice.pdf`);
const receiptPdfUrl = computed(() => `/api/hotels/reservation/${magicToken.value}/receipt.pdf`);

const nextSteps = computed(() => [
  {
    title: "Payment confirmed",
    description: isPaid.value
      ? "Your payment has been received and recorded."
      : "We'll confirm your payment status within a few minutes.",
    done: isPaid.value,
  },
  {
    title: "Confirmation email sent",
    description: isPaid.value
      ? "Check your inbox (and spam folder) for a confirmation with your booking details."
      : "We'll send a confirmation email once payment is verified.",
    done: isPaid.value,
  },
  {
    title: "Check-in voucher",
    description:
      reservation.value?.status === "voucher_sent"
        ? "Your voucher has been issued. Bring it to the hotel at check-in."
        : "Our team coordinates with the partner hotel and emails your voucher once confirmed.",
    done: reservation.value?.status === "voucher_sent",
  },
]);

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);

const formatShortDate = (iso) => {
  if (!iso) return "-";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleDateString("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
};
</script>
