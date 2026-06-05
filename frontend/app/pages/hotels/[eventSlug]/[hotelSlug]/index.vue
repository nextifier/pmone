<template>
  <div class="mx-auto max-w-6xl px-4 pt-4 pb-24 lg:pb-12">
    <div v-if="pending && !hotel" class="space-y-4">
      <!-- Hero skeleton: mirror HotelHeroCollapsible full state -->
      <div
        class="grid items-start gap-4 sm:grid-cols-[160px_1fr] lg:grid-cols-[160px_1fr_220px]"
      >
        <Skeleton class="aspect-4/5 w-full rounded-xl" />
        <div class="space-y-2">
          <Skeleton class="h-4 w-56 sm:h-5" />
          <Skeleton class="h-8 w-3/4 sm:h-9" />
          <div class="flex flex-wrap gap-1.5 pt-1">
            <Skeleton class="h-5 w-16 rounded-full" />
            <Skeleton class="h-5 w-20 rounded-full" />
            <Skeleton class="h-5 w-14 rounded-full" />
          </div>
          <Skeleton class="mt-1 h-4 w-2/3 sm:h-5" />
          <div class="space-y-1.5 pt-1">
            <Skeleton class="h-3.5 w-full" />
            <Skeleton class="h-3.5 w-11/12" />
            <Skeleton class="h-3.5 w-3/4" />
          </div>
        </div>
        <Skeleton
          class="hidden aspect-4/5 w-full rounded-xl lg:block lg:aspect-auto lg:h-full lg:min-h-[220px]"
        />
      </div>

      <!-- Stepper skeleton: mirror BookingStepper -->
      <div class="bg-background/95 sticky top-0 z-30 -mx-4 border-b px-4 py-3">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-2 sm:gap-4">
          <div
            v-for="i in 4"
            :key="`step-${i}`"
            class="flex flex-1 items-center justify-center gap-2 sm:justify-start"
          >
            <Skeleton class="size-7 shrink-0 rounded-full" />
            <Skeleton class="hidden h-4 w-24 sm:block" />
            <Skeleton v-if="i < 4" class="hidden h-px flex-1 sm:block" />
          </div>
        </div>
      </div>

      <!-- Step 1 layout: form + summary panel -->
      <div class="grid items-start gap-6 lg:grid-cols-[1fr_360px]">
        <div class="space-y-6">
          <div class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end sm:gap-4">
            <div class="space-y-1.5">
              <Skeleton class="h-3.5 w-32 sm:h-4" />
              <Skeleton class="h-9 w-full rounded-md" />
            </div>
            <div class="space-y-1.5">
              <Skeleton class="h-3.5 w-14 sm:h-4" />
              <Skeleton class="h-9 w-32 rounded-md" />
            </div>
          </div>
          <div class="space-y-3">
            <Skeleton class="h-5 w-28" />
            <Skeleton
              v-for="i in 3"
              :key="`room-${i}`"
              class="h-32 w-full rounded-xl"
            />
          </div>
        </div>
        <div class="hidden space-y-4 rounded-2xl border p-4 lg:block lg:p-5">
          <Skeleton class="h-5 w-32" />
          <div class="space-y-2">
            <Skeleton class="h-3 w-16" />
            <Skeleton class="h-4 w-40" />
            <Skeleton class="h-3 w-28" />
          </div>
          <div class="space-y-1.5 border-t pt-3">
            <Skeleton class="h-4 w-full" />
            <Skeleton class="h-4 w-5/6" />
          </div>
          <Skeleton class="h-9 w-full rounded-md" />
        </div>
      </div>
    </div>

    <Empty v-else-if="!hotel" class="mt-12 border">
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <Icon name="hugeicons:hotel-01" />
        </EmptyMedia>
        <EmptyTitle>Hotel reservation not available</EmptyTitle>
        <EmptyDescription>
          This hotel is not currently open for online booking. It may be temporarily disabled or
          the event has not enabled reservations yet.
        </EmptyDescription>
      </EmptyHeader>
      <EmptyContent>
        <NuxtLink
          to="/hotels"
          class="text-primary inline-flex items-center gap-x-1 text-sm font-medium tracking-tight hover:underline"
        >
          <Icon name="hugeicons:arrow-left-01" class="size-4 shrink-0" />
          Browse other hotels
        </NuxtLink>
      </EmptyContent>
    </Empty>

    <ClientOnly v-else>
      <div class="space-y-4">
        <HotelHeroCollapsible :hotel="hotel" :collapsed="bookingStore.currentStep > 1" />
        <BookingStepper
          v-model:step="currentStep"
          :can-step2="bookingStore.canProceedStep1"
          :can-step3="bookingStore.canProceedStep1"
          :can-step4="bookingStore.canProceedStep3"
          :has-addons="hasAddons"
        />

        <div class="grid items-start gap-6 lg:grid-cols-[1fr_360px]">
          <main>
            <BookingStep1Dates
              v-if="currentStep === 1"
              :hotel="hotel"
              :pricing-data="bookingStore.pricingAggregate"
              :pricing-loading="bookingStore.pricingLoading"
              :availability="bookingStore.availability"
              :room-previews="bookingStore.roomPreviews"
              :checking-availability="bookingStore.checkingAvailability"
              :check-in="bookingStore.checkIn"
              :check-out="bookingStore.checkOut"
              :guest-count="bookingStore.guestCount"
              :rooms="bookingStore.rooms"
              :room-notes="bookingStore.roomNotes"
              :nights="bookingStore.nights"
              @update:check-in="(v) => bookingStore.setDates({ checkIn: v, checkOut: bookingStore.checkOut })"
              @update:check-out="(v) => bookingStore.setDates({ checkIn: bookingStore.checkIn, checkOut: v })"
              @update:guest-count="(v) => bookingStore.setGuestCount(v)"
              @update:room-qty="({ roomId, qty }) => bookingStore.setRoomQty(roomId, qty)"
              @update:room-notes="({ roomId, notes }) => bookingStore.setRoomNotes(roomId, notes)"
              @month-change="onMonthChange"
            />

            <BookingStep2Addons
              v-else-if="currentStep === 2"
              :options="hotel.transfer_options ?? []"
              :selected="bookingStore.transfers"
              :notes="bookingStore.transferNotes"
              @toggle="({ id, on }) => bookingStore.toggleTransfer(id, on)"
              @update:notes="({ id, notes }) => bookingStore.setTransferNotes(id, notes)"
            />

            <BookingStep3Guest
              v-else-if="currentStep === 3"
              :guest="bookingStore.guest"
              :errors="submitErrors"
              :prefilled-from-profile="prefilledFromProfile"
              @update="(patch) => bookingStore.setGuest(patch)"
            />

            <BookingStep4Review
              v-else
              :hotel="hotel"
              :check-in="bookingStore.checkIn"
              :check-out="bookingStore.checkOut"
              :nights="bookingStore.nights"
              :guest-count="bookingStore.guestCount"
              :rooms="bookingStore.rooms"
              :room-notes="bookingStore.roomNotes"
              :transfers="bookingStore.transfers"
              :transfer-notes="bookingStore.transferNotes"
              :guest="bookingStore.guest"
              :room-previews="bookingStore.roomPreviews"
              :accept-terms="bookingStore.acceptTerms"
              :errors="submitErrors"
              :saving="submitting"
              @accept-terms="(v) => bookingStore.setAcceptTerms(v)"
              @edit="goToStep"
              @submit="handleSubmit"
            />
          </main>

          <aside class="hidden lg:block">
            <BookingSummary
              :step="currentStep"
              :hotel="hotel"
              :check-in="bookingStore.checkIn"
              :check-out="bookingStore.checkOut"
              :nights="bookingStore.nights"
              :guest-count="bookingStore.guestCount"
              :rooms="bookingStore.rooms"
              :transfers="bookingStore.transfers"
              :room-previews="bookingStore.roomPreviews"
              :tax-percentage="hotel.tax_percentage"
              :service-percentage="hotel.service_charge_percentage"
              :cta-label="ctaLabel"
              :cta-disabled="!ctaEnabled"
              :submitting="submitting"
              @primary="handlePrimary"
            />
          </aside>
        </div>
      </div>

      <MobileBottomCTA
        :total="mobileTotal"
        :cta-label="ctaLabel"
        :cta-disabled="!ctaEnabled"
        :submitting="submitting"
        @primary="handlePrimary"
      />
    </ClientOnly>
  </div>
</template>

<script setup>
import BookingStep1Dates from "../../../../components/hotels/BookingStep1Dates.vue";
import BookingStep2Addons from "../../../../components/hotels/BookingStep2Addons.vue";
import BookingStep3Guest from "../../../../components/hotels/BookingStep3Guest.vue";
import BookingStep4Review from "../../../../components/hotels/BookingStep4Review.vue";
import BookingStepper from "../../../../components/hotels/BookingStepper.vue";
import BookingSummary from "../../../../components/hotels/BookingSummary.vue";
import HotelHeroCollapsible from "../../../../components/hotels/HotelHeroCollapsible.vue";
import MobileBottomCTA from "../../../../components/hotels/MobileBottomCTA.vue";
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "../../../../components/ui/empty";
import { Skeleton } from "../../../../components/ui/skeleton";
import { useBookingStore } from "../../../../stores/booking";
import { toast } from "vue-sonner";
import { computed, onMounted, ref, watch } from "vue";

definePageMeta({ layout: "default", noFooter: true });

const route = useRoute();
const router = useRouter();
const eventSlug = computed(() => route.params.eventSlug);
const hotelSlug = computed(() => route.params.hotelSlug);

const { data, pending } = await useLazyAsyncData(
  () => `public-hotel-${eventSlug.value}-${hotelSlug.value}`,
  () => $fetch(`/api/hotels/${eventSlug.value}/${hotelSlug.value}`)
);

const hotel = computed(() => data.value?.data);

usePageMeta(null, {
  title: computed(
    () => `${hotel.value?.name ?? "Hotel"} · ${hotel.value?.event?.title ?? "Accommodation"}`
  ),
});

const bookingStore = useBookingStore();

const hasAddons = computed(
  () => (hotel.value?.transfer_options?.length ?? 0) > 0
);

const currentStep = computed({
  get: () => bookingStore.currentStep,
  set: (v) => {
    bookingStore.setStep(v);
    router.replace({ query: { ...route.query, step: String(v) } });
  },
});

const submitting = ref(false);
const submitErrors = ref({});
const prefilledFromProfile = ref(false);

let pricingDebounceTimer = null;
let availabilityReq = 0;

onMounted(async () => {
  bookingStore.hydrate();

  if (hotel.value) {
    bookingStore.setHotelContext({
      hotelId: hotel.value.id,
      eventId: hotel.value.event?.id ?? null,
      eventSlug: eventSlug.value,
      hotelSlug: hotelSlug.value,
    });

    // Clear stale persisted dates that fall outside this event's window
    // (e.g. visitor previously booked a different event). Within-range
    // persisted dates from a prior session are kept so a page refresh
    // doesn't wipe the user's picks. Never auto-fill from the event
    // start_date — that misrepresents intent before the user has chosen.
    const start = hotel.value.event?.start_date;
    const end = hotel.value.event?.end_date;
    if (start && end) {
      const ci = bookingStore.checkIn;
      const co = bookingStore.checkOut;
      const startDate = String(start).slice(0, 10);
      const endDate = String(end).slice(0, 10);

      if (ci && co && (ci < startDate || co > endDate)) {
        bookingStore.setDates({ checkIn: null, checkOut: null });
      }
    }
  }

  const stepParam = Number(route.query.step);
  if (stepParam >= 1 && stepParam <= 4) {
    // If the URL deep-links to the add-ons step but this hotel has no
    // transfer options, jump straight to the guest info step instead.
    if (stepParam === 2 && !hasAddons.value) {
      bookingStore.setStep(3);
    } else {
      bookingStore.setStep(stepParam);
    }
  }

  await tryPrefillFromUser();
});

async function tryPrefillFromUser() {
  if (
    bookingStore.guest.name ||
    bookingStore.guest.email ||
    bookingStore.guest.phone
  ) {
    return;
  }
  // `useOptionalUser` resolves the logged-in user when an auth system is
  // available (pmone admin, backed by Sanctum) and a null ref otherwise
  // (public event sites in pmone-events). Reading `user.value` is a pure
  // ref read — no network call — so visitors without a session get a clean
  // null. Keeping this call identical lets the page copy-paste across repos.
  const { user } = useOptionalUser();
  const u = user.value;
  if (u?.email) {
    bookingStore.setGuest({
      name: u.name || "",
      email: u.email || "",
      phone: u.phone || "",
    });
    prefilledFromProfile.value = true;
  }
}

watch(
  hotel,
  (h) => {
    if (!h || !import.meta.client) return;
    bookingStore.setHotelContext({
      hotelId: h.id,
      eventId: h.event?.id ?? null,
      eventSlug: eventSlug.value,
      hotelSlug: hotelSlug.value,
    });

    const start = h.event?.start_date;
    const end = h.event?.end_date;
    if (start && end) {
      bookingStore.fetchPricingAggregate({
        eventSlug: eventSlug.value,
        hotelSlug: hotelSlug.value,
        startDate: start,
        endDate: end,
      });
    }
  },
  { immediate: true, flush: "post" }
);

// If the persisted step (from a prior session) lands the user on the
// add-ons step but this hotel has no transfer options, bounce them forward
// to guest info. Skipped on step 1 so first-time visitors stay on dates.
watch(
  [hasAddons, () => bookingStore.currentStep],
  ([addons, step]) => {
    if (!addons && step === 2) {
      currentStep.value = 3;
    }
  },
  { flush: "post" }
);

watch(
  [() => bookingStore.checkIn, () => bookingStore.checkOut, hotel],
  async () => {
    if (!hotel.value || !bookingStore.checkIn || !bookingStore.checkOut || bookingStore.nights < 1)
      return;
    const reqId = ++availabilityReq;
    await bookingStore.fetchRoomAvailability({
      hotelId: hotel.value.id,
      rooms: hotel.value.room_types ?? [],
      checkIn: bookingStore.checkIn,
      checkOut: bookingStore.checkOut,
    });
    if (reqId !== availabilityReq) return;
  },
  { flush: "post" }
);

function onMonthChange({ start, end }) {
  clearTimeout(pricingDebounceTimer);
  pricingDebounceTimer = setTimeout(() => {
    bookingStore.fetchPricingAggregate({
      eventSlug: eventSlug.value,
      hotelSlug: hotelSlug.value,
      startDate: start,
      endDate: end,
    });
  }, 250);
}

function goToStep(n) {
  // Skip the add-ons step entirely when the hotel has no transfer options.
  if (n === 2 && !hasAddons.value) {
    currentStep.value = 3;
    return;
  }
  currentStep.value = n;
}

const ctaLabel = computed(() => {
  if (currentStep.value === 4) return "Confirm & Pay";
  return "Continue";
});

const ctaEnabled = computed(() => {
  if (currentStep.value === 1) return bookingStore.canProceedStep1;
  if (currentStep.value === 2) return bookingStore.canProceedStep2;
  if (currentStep.value === 3) return bookingStore.canProceedStep3;
  if (currentStep.value === 4) return bookingStore.canProceedStep4;
  return false;
});

const mobileTotal = computed(() => {
  if (!hotel.value) return 0;
  let rooms = 0;
  for (const room of hotel.value.room_types ?? []) {
    const qty = Number(bookingStore.rooms[room.id]) || 0;
    if (qty <= 0) continue;
    const preview = bookingStore.roomPreviews[room.id];
    if (preview && Number(preview.subtotal) > 0) {
      rooms += Number(preview.subtotal) * qty;
    } else {
      rooms += Number(room.base_rate) * bookingStore.nights * qty;
    }
  }
  let transfer = 0;
  for (const opt of hotel.value.transfer_options ?? []) {
    if (bookingStore.transfers[opt.id]) transfer += opt.price;
  }
  const sub = rooms + transfer;
  const tax = Math.round(sub * (hotel.value.tax_percentage / 100) * 100) / 100;
  const svc = Math.round(sub * (hotel.value.service_charge_percentage / 100) * 100) / 100;
  return sub + tax + svc;
});

function handlePrimary() {
  if (currentStep.value < 4) {
    if (!ctaEnabled.value) return;
    let next = currentStep.value + 1;
    // Skip the add-ons step entirely when the hotel has no transfer options.
    if (next === 2 && !hasAddons.value) {
      next = 3;
    }
    currentStep.value = next;
    return;
  }
  handleSubmit();
}

const ALLOWED_PAYMENT_HOSTS = [
  "checkout.xendit.co",
  "checkout-staging.xendit.co",
  "invoice.xendit.co",
  "invoice-staging.xendit.co",
  // Xendit Sessions (Payment Link) hosted checkout uses xen.to short links
  // (dev.xen.to in test mode). The endsWith check covers any *.xen.to host.
  "xen.to",
  // Midtrans Snap hosted checkout (redirect_url host): app.sandbox.midtrans.com
  // in sandbox, app.midtrans.com in production.
  "app.midtrans.com",
  "app.sandbox.midtrans.com",
];

function isAllowedPaymentUrl(url) {
  try {
    const u = new URL(url);
    return ALLOWED_PAYMENT_HOSTS.some((h) => u.host === h || u.host.endsWith(`.${h}`));
  } catch {
    return false;
  }
}

async function handleSubmit() {
  if (!bookingStore.canProceedStep4) return;
  submitting.value = true;
  submitErrors.value = {};

  const items = [];
  for (const room of hotel.value.room_types ?? []) {
    const qty = Number(bookingStore.rooms[room.id]) || 0;
    if (qty <= 0) continue;
    items.push({
      room_type_id: room.id,
      check_in_date: bookingStore.checkIn,
      check_out_date: bookingStore.checkOut,
      qty,
      notes: bookingStore.roomNotes[room.id] || null,
    });
  }

  const transfers = [];
  for (const opt of hotel.value.transfer_options ?? []) {
    if (!bookingStore.transfers[opt.id]) continue;
    transfers.push({
      transfer_option_id: opt.id,
      direction: opt.direction,
      transfer_date: opt.direction === "in" ? bookingStore.checkIn : bookingStore.checkOut,
      pax_count: 1,
      price: opt.price,
      note: bookingStore.transferNotes[opt.id] || null,
    });
  }

  try {
    const payload = {
      hotel_id: hotel.value.id,
      event_id: hotel.value.event?.id ?? bookingStore.eventId,
      guest_name: bookingStore.guest.name?.trim(),
      guest_email: bookingStore.guest.email?.trim(),
      guest_phone: String(bookingStore.guest.phone || "").trim(),
      guest_identity_type: bookingStore.guest.identity_type,
      guest_identity_number: bookingStore.guest.identity_number?.trim(),
      guest_nationality: bookingStore.guest.nationality,
      guest_company: bookingStore.guest.company,
      special_request: bookingStore.guest.special_request,
      accept_terms: bookingStore.acceptTerms,
      items,
      transfers,
    };

    if (bookingStore.promoCode) {
      payload.promo_code = bookingStore.promoCode;
    }

    const res = await $fetch("/api/hotels/book", { method: "POST", body: payload });
    const result = res?.data ?? res;

    if (result?.payment_url && isAllowedPaymentUrl(result.payment_url)) {
      bookingStore.reset();
      window.location.href = result.payment_url;
      return;
    }

    if (result?.magic_link_token) {
      bookingStore.reset();
      await navigateTo(`/hotels/reservation/${result.magic_link_token}`);
      return;
    }

    toast.success("Reservation created successfully.");
  } catch (err) {
    const data = err?.data || {};
    submitErrors.value = data.errors || {};
    const message = data.message || "Failed to process reservation. Please try again.";
    toast.error(message);
    if (currentStep.value === 4 && Object.keys(submitErrors.value).length) {
      // If there are guest field errors, go back to step 3
      const guestKeys = Object.keys(submitErrors.value).filter((k) => k.startsWith("guest_"));
      if (guestKeys.length) currentStep.value = 3;
    }
  } finally {
    submitting.value = false;
  }
}
</script>
