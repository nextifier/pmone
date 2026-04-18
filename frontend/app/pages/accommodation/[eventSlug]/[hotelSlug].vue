<template>
  <div class="mx-auto max-w-5xl px-4 py-8 sm:py-12">
    <NuxtLink to="/accommodation" class="inline-flex items-center gap-1 text-sm tracking-tight text-muted-foreground hover:text-primary">
      <Icon name="lucide:arrow-left" class="size-4" />
      Back to all hotels
    </NuxtLink>

    <div v-if="pending" class="mt-10 flex justify-center">
      <Spinner class="size-6" />
    </div>

    <div v-else-if="!hotel" class="mt-10 text-center text-sm tracking-tight text-muted-foreground">
      Hotel not found.
    </div>

    <div v-else class="mt-6 space-y-8">
      <div v-if="hotel.event" class="text-muted-foreground text-xs sm:text-sm tracking-tight">
        <span>{{ hotel.event.title }}</span>
        <span v-if="hotel.event.start_date || hotel.event.end_date"> · {{ formatEventDates(hotel.event) }}</span>
      </div>

      <HotelDetailHeader :hotel="hotel" />

      <section class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div class="space-y-4">
          <RoomTypeSelector :rooms="hotel.room_types" v-model="selectedRoomQty" />
          <TransferSelector :options="hotel.transfer_options" v-model="selectedTransfers" />
        </div>

        <aside>
          <BookingSummary
            v-model:check-in="checkInDate"
            v-model:check-out="checkOutDate"
            :summary="summary"
            :tax-percentage="hotel.tax_percentage"
            :service-percentage="hotel.service_charge_percentage"
            :can-proceed="canProceed"
            @continue="formDialogOpen = true"
          />
        </aside>
      </section>
    </div>

    <DialogResponsive v-model:open="formDialogOpen" :overflow-content="true" dialog-max-width="34rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Guest Information</h3>
          <p class="text-muted-foreground text-sm tracking-tight mt-1">Please fill in details matching your official identity document.</p>

          <GuestInfoForm :saving="saving" @submit="handleSubmit" @cancel="formDialogOpen = false" />
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import HotelDetailHeader from "@/components/accommodation/HotelDetailHeader.vue";
import RoomTypeSelector from "@/components/accommodation/RoomTypeSelector.vue";
import TransferSelector from "@/components/accommodation/TransferSelector.vue";
import BookingSummary from "@/components/accommodation/BookingSummary.vue";
import GuestInfoForm from "@/components/accommodation/GuestInfoForm.vue";
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { computed, ref } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  layout: "public",
});

const route = useRoute();
const eventSlug = computed(() => route.params.eventSlug);
const hotelSlug = computed(() => route.params.hotelSlug);

const { data, pending } = await useLazyAsyncData(
  () => `public-hotel-${eventSlug.value}-${hotelSlug.value}`,
  () => $fetch(`/api/accommodation/events/${eventSlug.value}/hotels/${hotelSlug.value}`),
);

const hotel = computed(() => data.value?.data);

usePageMeta(null, {
  title: computed(() => `${hotel.value?.name ?? "Hotel"} · ${hotel.value?.event?.title ?? "Accommodation"}`),
});

const today = new Date();
const tomorrow = new Date(today.getTime() + 86400000);
const dayAfter = new Date(today.getTime() + 2 * 86400000);
const checkInDate = ref(tomorrow);
const checkOutDate = ref(dayAfter);

const selectedRoomQty = ref({});
const selectedTransfers = ref({});

const toIsoDate = (d) => {
  if (!d) return null;
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

const nights = computed(() => {
  if (!checkInDate.value || !checkOutDate.value) return 0;
  const ms = checkOutDate.value.getTime() - checkInDate.value.getTime();
  return Math.max(0, Math.round(ms / 86400000));
});

const summary = computed(() => {
  if (!hotel.value) return { rooms: 0, transfer: 0, tax: 0, service: 0, total: 0 };
  let rooms = 0;
  for (const room of hotel.value.room_types ?? []) {
    const qty = Number(selectedRoomQty.value[room.id]) || 0;
    rooms += room.base_rate * nights.value * qty;
  }
  let transfer = 0;
  for (const opt of hotel.value.transfer_options ?? []) {
    if (selectedTransfers.value[opt.id]) transfer += opt.price;
  }
  const taxBase = rooms + transfer;
  const tax = Math.round(taxBase * (hotel.value.tax_percentage / 100) * 100) / 100;
  const service = Math.round(taxBase * (hotel.value.service_charge_percentage / 100) * 100) / 100;
  const total = taxBase + tax + service;
  return { rooms, transfer, tax, service, total };
});

const canProceed = computed(() => summary.value.total > 0 && nights.value > 0);

const formDialogOpen = ref(false);
const saving = ref(false);

const formatEventDates = (ev) => {
  const fmt = (d) => d ? new Date(d).toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" }) : "";
  if (ev.start_date && ev.end_date) return `${fmt(ev.start_date)} – ${fmt(ev.end_date)}`;
  return fmt(ev.start_date || ev.end_date);
};

const handleSubmit = async (guestPayload) => {
  saving.value = true;
  try {
    const checkIn = toIsoDate(checkInDate.value);
    const checkOut = toIsoDate(checkOutDate.value);

    const items = [];
    for (const room of hotel.value.room_types ?? []) {
      const qty = Number(selectedRoomQty.value[room.id]) || 0;
      if (qty > 0) {
        items.push({
          room_type_id: room.id,
          check_in_date: checkIn,
          check_out_date: checkOut,
          qty,
        });
      }
    }

    const transfers = [];
    for (const opt of hotel.value.transfer_options ?? []) {
      if (selectedTransfers.value[opt.id]) {
        transfers.push({
          transfer_option_id: opt.id,
          direction: opt.direction === "both" ? "in" : opt.direction,
          transfer_date: checkIn,
          pax_count: 1,
          price: opt.price,
        });
      }
    }

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

    formDialogOpen.value = false;
    if (response?.data?.payment_url) {
      window.location.href = response.data.payment_url;
    } else if (response?.data?.magic_link_token) {
      await navigateTo(`/hotels/reservation/${response.data.magic_link_token}`);
    }
  } catch (err) {
    toast.error("Booking failed", { description: err?.data?.message || err?.message });
  } finally {
    saving.value = false;
  }
};
</script>
