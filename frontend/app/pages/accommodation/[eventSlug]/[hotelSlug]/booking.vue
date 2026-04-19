<template>
  <div class="mx-auto max-w-5xl px-4 pt-4 pb-16 space-y-6">
    <Breadcrumb>
      <BreadcrumbList>
        <BreadcrumbItem>
          <BreadcrumbLink as-child>
            <NuxtLink to="/accommodation">Hotels</NuxtLink>
          </BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <BreadcrumbLink as-child>
            <NuxtLink :to="`/accommodation/${eventSlug}/${hotelSlug}`">
              {{ hotel?.name ?? "Hotel" }}
            </NuxtLink>
          </BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <BreadcrumbPage>Booking</BreadcrumbPage>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="flex items-center gap-x-2.5 min-w-0">
      <ButtonBack
        :destination="`/accommodation/${eventSlug}/${hotelSlug}`"
        :show-label="false"
      />
      <h1 class="page-title">Guest Information</h1>
    </div>

    <div v-if="pending" class="grid gap-6 lg:grid-cols-[1fr_360px]">
      <div class="space-y-4">
        <Skeleton class="h-10 w-1/2" />
        <Skeleton class="h-40 w-full" />
        <Skeleton class="h-40 w-full" />
      </div>
      <Skeleton class="h-72 w-full" />
    </div>

    <div
      v-else-if="!hotel"
      class="text-muted-foreground rounded-md border border-dashed py-12 text-center text-sm tracking-tight"
    >
      Hotel not found.
    </div>

    <ClientOnly v-else>
      <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div class="space-y-4">
          <div
            v-if="priceDrift"
            role="alert"
            class="bg-warning/10 text-warning-foreground rounded-md border border-warning/30 p-4 text-sm tracking-tight"
          >
            <p class="font-semibold">Price updated</p>
            <p class="mt-1">
              The hotel rate has changed since you started booking. New total:
              <strong>Rp {{ formatRupiah(summary.total) }}</strong>. Please accept the new price to continue.
            </p>
            <Button size="sm" class="mt-3" @click="acceptNewPrice">Accept new price</Button>
          </div>

          <GuestInfoForm
            v-model="guestModel"
            v-model:accept-terms="acceptTerms"
            :saving="saving"
            :errors="errors"
            :disabled="priceDrift"
            @submit="handleSubmit"
            @cancel="handleCancel"
          />
        </div>

        <aside>
          <div class="frame sticky top-4">
            <div class="frame-header">
              <div class="frame-title">Order Summary</div>
            </div>
            <div class="frame-panel space-y-4">
              <div class="space-y-1">
                <p class="text-sm font-medium tracking-tight">{{ hotel.name }}</p>
                <p v-if="hotel.event" class="text-muted-foreground text-xs tracking-tight">
                  {{ hotel.event.title }}
                </p>
              </div>

              <div class="border-t pt-3 text-sm tracking-tight space-y-1">
                <div class="flex justify-between">
                  <span class="text-muted-foreground">Check-in</span>
                  <span>{{ formatDate(checkIn) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-muted-foreground">Check-out</span>
                  <span>{{ formatDate(checkOut) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-muted-foreground">Duration</span>
                  <span>{{ nights }} night{{ nights > 1 ? "s" : "" }}</span>
                </div>
              </div>

              <div v-if="selectedRoomLines.length" class="border-t pt-3 space-y-1.5 text-sm tracking-tight">
                <p class="text-muted-foreground text-xs tracking-tight">Rooms</p>
                <div v-for="line in selectedRoomLines" :key="line.id" class="flex justify-between">
                  <span>{{ line.name }} × {{ line.qty }}</span>
                  <span class="tabular-nums">Rp {{ formatRupiah(line.subtotal) }}</span>
                </div>
              </div>

              <div v-if="selectedTransferLines.length" class="border-t pt-3 space-y-1.5 text-sm tracking-tight">
                <p class="text-muted-foreground text-xs tracking-tight">Transfer</p>
                <div v-for="line in selectedTransferLines" :key="line.id" class="flex justify-between">
                  <span>{{ line.label }}</span>
                  <span class="tabular-nums">Rp {{ formatRupiah(line.price) }}</span>
                </div>
              </div>

              <div class="border-t pt-3 space-y-1.5 text-sm tracking-tight">
                <div class="flex justify-between text-muted-foreground">
                  <span>Subtotal</span>
                  <span class="tabular-nums">
                    Rp {{ formatRupiah(summary.rooms + summary.transfer) }}
                  </span>
                </div>
                <div class="flex justify-between text-muted-foreground">
                  <span>Tax {{ hotel.tax_percentage }}%</span>
                  <span class="tabular-nums">Rp {{ formatRupiah(summary.tax) }}</span>
                </div>
                <div v-if="summary.service > 0" class="flex justify-between text-muted-foreground">
                  <span>Service {{ hotel.service_charge_percentage }}%</span>
                  <span class="tabular-nums">Rp {{ formatRupiah(summary.service) }}</span>
                </div>
                <div class="flex justify-between border-t pt-1.5 font-semibold">
                  <span>Total</span>
                  <span class="tabular-nums">Rp {{ formatRupiah(summary.total) }}</span>
                </div>
              </div>
            </div>
          </div>
        </aside>
      </div>

      <template #fallback>
        <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
          <div class="space-y-4">
            <Skeleton class="h-40 w-full" />
            <Skeleton class="h-40 w-full" />
          </div>
          <Skeleton class="h-72 w-full" />
        </div>
      </template>
    </ClientOnly>
  </div>
</template>

<script setup>
import GuestInfoForm from "@/components/accommodation/GuestInfoForm.vue";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";
import { Button } from "@/components/ui/button";
import { ButtonBack } from "@/components/ui/button-back";
import { Skeleton } from "@/components/ui/skeleton";
import { useBookingSession } from "@/composables/useBookingSession";
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { toast } from "vue-sonner";

const ALLOWED_PAYMENT_HOSTS = [
  "checkout.xendit.co",
  "checkout-staging.xendit.co",
  "invoice.xendit.co",
  "invoice-staging.xendit.co",
];

function isAllowedPaymentUrl(url) {
  if (!url || typeof url !== "string") return false;
  try {
    const parsed = new URL(url);
    if (parsed.protocol !== "https:") return false;
    return ALLOWED_PAYMENT_HOSTS.some(
      (host) => parsed.hostname === host || parsed.hostname.endsWith(`.${host}`),
    );
  } catch {
    return false;
  }
}

definePageMeta({
  layout: "public",
});

const route = useRoute();
const router = useRouter();
const eventSlug = computed(() => route.params.eventSlug);
const hotelSlug = computed(() => route.params.hotelSlug);

const { state, hydrate, setGuest, set, clear, hasBookingSelection } = useBookingSession();

const { data, pending } = await useLazyAsyncData(
  () => `public-hotel-booking-${eventSlug.value}-${hotelSlug.value}`,
  () => $fetch(`/api/accommodation/events/${eventSlug.value}/hotels/${hotelSlug.value}`),
);

const hotel = computed(() => data.value?.data);

usePageMeta(null, {
  title: computed(() => `Booking · ${hotel.value?.name ?? "Hotel"}`),
});

const parseIso = (iso) => {
  if (!iso) return null;
  const [y, m, d] = iso.split("-").map(Number);
  if (!y || !m || !d) return null;
  const date = new Date(y, m - 1, d);
  return Number.isNaN(date.getTime()) ? null : date;
};

const checkIn = computed(() => parseIso(state.value.checkIn));
const checkOut = computed(() => parseIso(state.value.checkOut));

const nights = computed(() => {
  if (!checkIn.value || !checkOut.value) return 0;
  return Math.max(0, Math.round((checkOut.value - checkIn.value) / 86400000));
});

const selectedRoomLines = computed(() => {
  if (!hotel.value?.room_types) return [];
  const qtys = state.value.rooms || {};
  return hotel.value.room_types
    .filter((room) => Number(qtys[room.id]) > 0)
    .map((room) => {
      const qty = Number(qtys[room.id]) || 0;
      return {
        id: room.id,
        name: room.name,
        qty,
        rate: room.base_rate,
        subtotal: room.base_rate * nights.value * qty,
      };
    });
});

const selectedTransferLines = computed(() => {
  if (!hotel.value?.transfer_options) return [];
  const flags = state.value.transfers || {};
  return hotel.value.transfer_options
    .filter((opt) => flags[opt.id])
    .map((opt) => ({ id: opt.id, label: opt.label, price: opt.price }));
});

const summary = computed(() => {
  if (!hotel.value) return { rooms: 0, transfer: 0, tax: 0, service: 0, total: 0 };
  const rooms = selectedRoomLines.value.reduce((sum, line) => sum + line.subtotal, 0);
  const transfer = selectedTransferLines.value.reduce((sum, line) => sum + line.price, 0);
  const taxBase = rooms + transfer;
  const tax = Math.round(taxBase * (hotel.value.tax_percentage / 100) * 100) / 100;
  const service =
    Math.round(taxBase * (hotel.value.service_charge_percentage / 100) * 100) / 100;
  const total = taxBase + tax + service;
  return { rooms, transfer, tax, service, total };
});

const sessionTotal = ref(null);
const priceDrift = computed(() => {
  if (sessionTotal.value === null) return false;
  return Math.abs(sessionTotal.value - summary.value.total) > 0.01;
});

const guestModel = ref({
  name: "",
  email: "",
  phone: "",
  identity_type: "nik",
  identity_number: "",
  nationality: "Indonesia",
  company: "",
  special_request: "",
});
const acceptTerms = ref(false);
const saving = ref(false);
const errors = ref({});

const isFormDirty = computed(() => {
  const g = guestModel.value || {};
  return Boolean(
    g.name || g.email || g.phone || g.identity_number || g.company || g.special_request,
  );
});

watch(
  guestModel,
  (value) => setGuest(value),
  { deep: true, flush: "post" },
);

watch(acceptTerms, (value) => set({ acceptTerms: value }), { flush: "post" });

function beforeUnloadHandler(event) {
  if (!isFormDirty.value || saving.value) return;
  event.preventDefault();
  event.returnValue = "";
}

onMounted(() => {
  hydrate();

  if (
    !hasBookingSelection() ||
    state.value.eventSlug !== eventSlug.value ||
    state.value.hotelSlug !== hotelSlug.value
  ) {
    router.replace(`/accommodation/${eventSlug.value}/${hotelSlug.value}`);
    return;
  }

  guestModel.value = { ...guestModel.value, ...(state.value.guest || {}) };
  acceptTerms.value = Boolean(state.value.acceptTerms);

  if (hotel.value && sessionTotal.value === null) {
    sessionTotal.value = summary.value.total;
  } else {
    const stopWatch = watch(hotel, (value) => {
      if (!value) return;
      if (sessionTotal.value === null) {
        sessionTotal.value = summary.value.total;
      }
      stopWatch();
    });
  }

  window.addEventListener("beforeunload", beforeUnloadHandler);
});

onBeforeUnmount(() => {
  if (import.meta.client) {
    window.removeEventListener("beforeunload", beforeUnloadHandler);
  }
});

function handleCancel() {
  router.push(`/accommodation/${eventSlug.value}/${hotelSlug.value}`);
}

function acceptNewPrice() {
  sessionTotal.value = summary.value.total;
}

async function recheckAvailability() {
  if (!hotel.value) return true;
  const lines = selectedRoomLines.value;
  for (const line of lines) {
    try {
      const res = await $fetch("/api/accommodation/availability", {
        method: "POST",
        body: {
          hotel_id: hotel.value.id,
          room_type_id: line.id,
          check_in_date: state.value.checkIn,
          check_out_date: state.value.checkOut,
          qty: line.qty,
        },
      });
      const available = Number(res?.data?.available ?? 0);
      if (available < line.qty) {
        toast.error("Room no longer available", {
          description: `${line.name}: only ${available} left. Please adjust your selection.`,
        });
        return false;
      }
    } catch (err) {
      toast.error("Availability check failed", {
        description: err?.data?.message || err?.message || "Please try again.",
      });
      return false;
    }
  }
  return true;
}

async function handleSubmit(guestPayload) {
  if (!hotel.value) return;
  if (priceDrift.value) {
    toast.error("Price has changed", {
      description: "Please accept the new price before continuing.",
    });
    return;
  }

  errors.value = {};
  saving.value = true;

  try {
    const ok = await recheckAvailability();
    if (!ok) {
      saving.value = false;
      return;
    }

    const items = selectedRoomLines.value.map((line) => ({
      room_type_id: line.id,
      check_in_date: state.value.checkIn,
      check_out_date: state.value.checkOut,
      qty: line.qty,
    }));

    const transfers = selectedTransferLines.value.map((line) => {
      const opt = hotel.value.transfer_options.find((o) => o.id === line.id);
      return {
        transfer_option_id: line.id,
        direction: opt?.direction === "both" ? "in" : opt?.direction,
        transfer_date: state.value.checkIn,
        pax_count: 1,
        price: line.price,
      };
    });

    const payload = {
      hotel_id: hotel.value.id,
      ...guestPayload,
      items,
      transfers,
    };

    const response = await $fetch("/api/accommodation/book", {
      method: "POST",
      body: payload,
    });

    clear();

    const paymentUrl = response?.data?.payment_url;
    if (paymentUrl) {
      if (!isAllowedPaymentUrl(paymentUrl)) {
        toast.error("Payment redirect blocked", {
          description: "Payment URL is not from a trusted provider. Please contact support.",
        });
        return;
      }
      window.location.href = paymentUrl;
    } else if (response?.data?.magic_link_token) {
      await navigateTo(`/hotels/reservation/${response.data.magic_link_token}`);
    }
  } catch (err) {
    if (err?.response?.status === 422 && err?.data?.errors) {
      errors.value = err.data.errors;
    }
    toast.error("Booking failed", {
      description: err?.data?.message || err?.message || "Please check the form and try again.",
    });
  } finally {
    saving.value = false;
  }
}

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);
const formatDate = (date) => {
  if (!date) return "-";
  return date.toLocaleDateString("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
};
</script>
