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
      <section class="space-y-4">
        <div v-if="hotel.featured?.lg" class="overflow-hidden rounded-lg">
          <img :src="hotel.featured.lg" :alt="hotel.name" class="aspect-video w-full object-cover" />
        </div>

        <div class="space-y-2">
          <h1 class="text-2xl sm:text-3xl font-semibold tracking-tighter">{{ hotel.name }}</h1>
          <p class="text-muted-foreground text-sm tracking-tight">{{ [hotel.address, hotel.city].filter(Boolean).join(", ") }}</p>
          <p v-if="hotel.description" class="text-sm tracking-tight whitespace-pre-line">{{ hotel.description }}</p>
        </div>

        <div v-if="hotel.gallery?.length" class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
          <div v-for="img in hotel.gallery" :key="img.id" class="bg-muted aspect-square overflow-hidden rounded">
            <img :src="img.sm" :alt="hotel.name" class="size-full object-cover" />
          </div>
        </div>
      </section>

      <section class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div class="space-y-4">
          <h2 class="text-lg font-semibold tracking-tight">Available Rooms</h2>

          <div v-if="!hotel.room_types?.length" class="text-muted-foreground text-sm tracking-tight">
            No rooms available.
          </div>

          <div v-else class="space-y-3">
            <div v-for="room in hotel.room_types" :key="room.id" class="rounded-md border p-4 space-y-3">
              <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="min-w-0 flex-1">
                  <h3 class="text-base font-semibold tracking-tight">{{ room.name }}</h3>
                  <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
                    {{ room.bed_type || "-" }} · max {{ room.max_pax }} pax · {{ room.area_sqm ? `${room.area_sqm} m²` : "-" }}
                  </p>
                  <div v-if="room.amenities?.length" class="mt-1 flex flex-wrap gap-1">
                    <span v-for="a in room.amenities.slice(0, 5)" :key="a" class="bg-muted rounded-full px-2 py-0.5 text-xs tracking-tight">{{ a }}</span>
                  </div>
                </div>
                <div class="text-right">
                  <p class="text-lg sm:text-xl font-semibold tracking-tighter">Rp {{ formatRupiah(room.base_rate) }} <span class="text-muted-foreground text-xs sm:text-sm font-normal">/ malam</span></p>
                  <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">Rp {{ formatRupiah(room.all_in_rate) }} sudah termasuk pajak & service</p>
                </div>
              </div>

              <div class="flex items-center gap-2">
                <Label class="text-xs sm:text-sm">Qty:</Label>
                <Input v-model.number="selectedRoomQty[room.id]" type="number" min="0" max="20" class="w-20" />
              </div>
            </div>
          </div>

          <div v-if="hotel.transfer_options?.length" class="space-y-3 pt-4">
            <h2 class="text-lg font-semibold tracking-tight">Transfer (Optional)</h2>
            <div v-for="opt in hotel.transfer_options" :key="opt.id" class="rounded-md border p-4 space-y-3">
              <label class="flex items-start gap-3 cursor-pointer">
                <Checkbox v-model="selectedTransfers[opt.id]" />
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium tracking-tight">{{ opt.label }}</p>
                  <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">{{ opt.direction_label }} · {{ opt.vehicle_type || "-" }} · max {{ opt.max_pax }} pax</p>
                </div>
                <p class="text-sm font-medium tracking-tight">Rp {{ formatRupiah(opt.price) }}</p>
              </label>
            </div>
          </div>
        </div>

        <aside class="space-y-4">
          <div class="rounded-lg border p-5 space-y-4 sticky top-4">
            <h3 class="text-base font-semibold tracking-tight">Booking</h3>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label>Check-in</Label>
                <Input v-model="checkInDate" type="date" required />
              </div>
              <div class="space-y-2">
                <Label>Check-out</Label>
                <Input v-model="checkOutDate" type="date" required />
              </div>
            </div>

            <div class="text-xs sm:text-sm tracking-tight space-y-1.5 border-t pt-3">
              <div class="flex justify-between"><span>Subtotal rooms</span><span>Rp {{ formatRupiah(summary.rooms) }}</span></div>
              <div class="flex justify-between"><span>Transfer</span><span>Rp {{ formatRupiah(summary.transfer) }}</span></div>
              <div class="flex justify-between text-muted-foreground"><span>Tax {{ hotel.tax_percentage }}%</span><span>Rp {{ formatRupiah(summary.tax) }}</span></div>
              <div class="flex justify-between text-muted-foreground" v-if="summary.service > 0"><span>Service {{ hotel.service_charge_percentage }}%</span><span>Rp {{ formatRupiah(summary.service) }}</span></div>
              <div class="flex justify-between font-semibold pt-1.5 border-t"><span>Total</span><span>Rp {{ formatRupiah(summary.total) }}</span></div>
            </div>

            <Button class="w-full" :disabled="!canProceed" @click="openGuestForm">
              Continue to Booking
            </Button>
          </div>
        </aside>
      </section>
    </div>

    <DialogResponsive v-model:open="formDialogOpen" :overflow-content="true" dialog-max-width="34rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Guest Information</h3>
          <p class="text-muted-foreground text-sm tracking-tight mt-1">Mohon isi data sesuai identitas resmi.</p>

          <form @submit.prevent="handleSubmit" class="mt-4 space-y-3">
            <div class="space-y-2">
              <Label>Full Name<span class="text-destructive">*</span></Label>
              <Input v-model="guest.name" required />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label>Email<span class="text-destructive">*</span></Label>
                <Input v-model="guest.email" type="email" required />
              </div>
              <div class="space-y-2">
                <Label>Phone<span class="text-destructive">*</span></Label>
                <Input v-model="guest.phone" required />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label>ID Type<span class="text-destructive">*</span></Label>
                <select v-model="guest.identity_type" required class="border-input w-full rounded-md border px-3 py-2 text-sm tracking-tight">
                  <option value="nik">NIK (KTP)</option>
                  <option value="passport">Passport</option>
                </select>
              </div>
              <div class="space-y-2">
                <Label>ID Number<span class="text-destructive">*</span></Label>
                <Input v-model="guest.identity_number" required />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label>Nationality</Label>
                <Input v-model="guest.nationality" placeholder="Indonesia" />
              </div>
              <div class="space-y-2">
                <Label>Company</Label>
                <Input v-model="guest.company" />
              </div>
            </div>
            <div class="space-y-2">
              <Label>Address</Label>
              <Textarea v-model="guest.address" rows="2" />
            </div>
            <div class="space-y-2">
              <Label>Special Request</Label>
              <Textarea v-model="guest.special_request" rows="2" placeholder="Late check-in, dietary, dll." />
            </div>

            <label class="flex items-start gap-2 text-sm tracking-tight pt-2">
              <Checkbox v-model="acceptTerms" required />
              <span>Saya setuju dengan <NuxtLink to="/terms" target="_blank" class="text-primary hover:underline">syarat & ketentuan</NuxtLink> serta <NuxtLink to="/privacy" target="_blank" class="text-primary hover:underline">kebijakan privasi</NuxtLink>.</span>
            </label>

            <div class="flex justify-end gap-2 pt-3">
              <Button type="button" variant="outline" @click="formDialogOpen = false">Cancel</Button>
              <Button type="submit" :disabled="!acceptTerms || saving">
                <Icon v-if="saving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                Confirm & Pay
              </Button>
            </div>
          </form>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { computed, reactive, ref } from "vue";
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
const tomorrow = new Date(today.getTime() + 86400000);
const dayAfter = new Date(today.getTime() + 2 * 86400000);
const checkInDate = ref(tomorrow.toISOString().slice(0, 10));
const checkOutDate = ref(dayAfter.toISOString().slice(0, 10));

const selectedRoomQty = reactive({});
const selectedTransfers = reactive({});

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);

const nights = computed(() => {
  if (!checkInDate.value || !checkOutDate.value) return 0;
  const ms = new Date(checkOutDate.value).getTime() - new Date(checkInDate.value).getTime();
  return Math.max(0, Math.round(ms / 86400000));
});

const summary = computed(() => {
  if (!hotel.value) return { rooms: 0, transfer: 0, tax: 0, service: 0, total: 0 };
  let rooms = 0;
  for (const room of hotel.value.room_types ?? []) {
    const qty = Number(selectedRoomQty[room.id]) || 0;
    rooms += room.base_rate * nights.value * qty;
  }
  let transfer = 0;
  for (const opt of hotel.value.transfer_options ?? []) {
    if (selectedTransfers[opt.id]) transfer += opt.price;
  }
  const taxBase = rooms + transfer;
  const tax = Math.round(taxBase * (hotel.value.tax_percentage / 100) * 100) / 100;
  const service = Math.round(taxBase * (hotel.value.service_charge_percentage / 100) * 100) / 100;
  const total = taxBase + tax + service;
  return { rooms, transfer, tax, service, total };
});

const canProceed = computed(() => {
  return summary.value.total > 0 && nights.value > 0;
});

const formDialogOpen = ref(false);
const saving = ref(false);
const acceptTerms = ref(false);

const guest = reactive({
  name: "",
  email: "",
  phone: "",
  identity_type: "nik",
  identity_number: "",
  nationality: "Indonesia",
  company: "",
  address: "",
  special_request: "",
});

const openGuestForm = () => {
  formDialogOpen.value = true;
};

const handleSubmit = async () => {
  saving.value = true;
  try {
    const items = [];
    for (const room of hotel.value.room_types ?? []) {
      const qty = Number(selectedRoomQty[room.id]) || 0;
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
      if (selectedTransfers[opt.id]) {
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
      ...guest,
      items,
      transfers,
      accept_terms: true,
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
