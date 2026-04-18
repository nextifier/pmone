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
            :min-check-in="minCheckIn"
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
const slug = computed(() => route.params.hotelSlug);

const { data, pending } = await useLazyAsyncData(() => `public-hotel-${slug.value}`, () => $fetch(`/api/accommodation/hotels/${slug.value}`));

const hotel = computed(() => data.value?.data);

usePageMeta(null, {
  title: computed(() => `${hotel.value?.name ?? "Hotel"} · Accommodation`),
});

const today = new Date();
const minCheckIn = today.toISOString().slice(0, 10);
const tomorrow = new Date(today.getTime() + 86400000);
const dayAfter = new Date(today.getTime() + 2 * 86400000);
const checkInDate = ref(tomorrow.toISOString().slice(0, 10));
const checkOutDate = ref(dayAfter.toISOString().slice(0, 10));

const selectedRoomQty = ref({});
const selectedTransfers = ref({});

const nights = computed(() => {
  if (!checkInDate.value || !checkOutDate.value) return 0;
  const ms = new Date(checkOutDate.value).getTime() - new Date(checkInDate.value).getTime();
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

const handleSubmit = async (guestPayload) => {
  saving.value = true;
  try {
    const items = [];
    for (const room of hotel.value.room_types ?? []) {
      const qty = Number(selectedRoomQty.value[room.id]) || 0;
      if (qty > 0) {
        items.push({
          room_type_id: room.id,
          check_in_date: checkInDate.value,
          check_out_date: checkOutDate.value,
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
          transfer_date: checkInDate.value,
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
