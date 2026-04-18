<template>
  <div class="mx-auto max-w-3xl px-4 py-8 sm:py-12">
    <div v-if="pending" class="flex justify-center py-10">
      <Spinner class="size-6" />
    </div>

    <div v-else-if="!reservation" class="text-center py-10">
      <p class="text-muted-foreground tracking-tight">Reservation not found.</p>
    </div>

    <div v-else class="space-y-6">
      <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
          <h1 class="text-2xl sm:text-3xl font-semibold tracking-tighter">Booking {{ reservation.reservation_number }}</h1>
          <p class="text-muted-foreground text-sm tracking-tight mt-1">{{ reservation.hotel?.name }}</p>
        </div>
        <span :class="['inline-flex items-center rounded-full px-3 py-1 text-xs sm:text-sm tracking-tight', statusClass]">
          {{ reservation.status_label }}
        </span>
      </div>

      <div class="rounded-lg border p-5 space-y-3">
        <h2 class="text-base font-semibold tracking-tight">Guest Information</h2>
        <div class="grid grid-cols-2 gap-3 text-sm tracking-tight">
          <div>
            <p class="text-muted-foreground text-xs">Name</p>
            <p>{{ reservation.guest.name }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs">Email</p>
            <p>{{ reservation.guest.email }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs">Phone</p>
            <p>{{ reservation.guest.phone }}</p>
          </div>
        </div>
      </div>

      <div class="rounded-lg border p-5 space-y-3">
        <h2 class="text-base font-semibold tracking-tight">Booking Details</h2>
        <div v-for="item in reservation.items" :key="item.room_type_name" class="border-b last:border-b-0 pb-2 last:pb-0">
          <div class="flex justify-between gap-3 text-sm tracking-tight">
            <div>
              <p class="font-medium">{{ item.room_type_name }}</p>
              <p class="text-muted-foreground text-xs">
                {{ item.check_in_date }} → {{ item.check_out_date }} · {{ item.nights }} malam · {{ item.qty }} kamar
              </p>
            </div>
            <p class="font-medium tabular-nums">Rp {{ formatRupiah(item.subtotal) }}</p>
          </div>
        </div>

        <div v-if="reservation.transfers?.length" class="border-t pt-3 space-y-2">
          <h3 class="text-sm font-medium tracking-tight">Transfer</h3>
          <div v-for="(t, idx) in reservation.transfers" :key="idx" class="flex justify-between gap-3 text-sm tracking-tight">
            <div>
              <p>{{ t.direction }} · {{ t.transfer_date }}</p>
              <p class="text-muted-foreground text-xs">{{ t.pickup_location || "-" }} → {{ t.dropoff_location || "-" }}</p>
            </div>
            <p class="tabular-nums">Rp {{ formatRupiah(t.price) }}</p>
          </div>
        </div>

        <div class="border-t pt-3 space-y-1.5 text-sm tracking-tight">
          <div class="flex justify-between text-muted-foreground"><span>Subtotal</span><span class="tabular-nums">Rp {{ formatRupiah(reservation.amounts.subtotal_rooms + reservation.amounts.subtotal_transfer) }}</span></div>
          <div class="flex justify-between text-muted-foreground"><span>Tax</span><span class="tabular-nums">Rp {{ formatRupiah(reservation.amounts.tax) }}</span></div>
          <div v-if="reservation.amounts.service > 0" class="flex justify-between text-muted-foreground"><span>Service</span><span class="tabular-nums">Rp {{ formatRupiah(reservation.amounts.service) }}</span></div>
          <div class="flex justify-between font-semibold text-base pt-1.5 border-t"><span>Total</span><span class="tabular-nums">Rp {{ formatRupiah(reservation.amounts.total) }}</span></div>
        </div>
      </div>

      <div v-if="reservation.status === 'pending_payment' && reservation.payment_url" class="rounded-lg border p-5 space-y-3">
        <h2 class="text-base font-semibold tracking-tight">Payment Pending</h2>
        <p class="text-sm tracking-tight">Pembayaran belum diterima. Silakan lanjutkan via Xendit:</p>
        <a :href="reservation.payment_url" class="bg-primary text-primary-foreground inline-block rounded-md px-4 py-2 text-sm font-medium tracking-tight hover:bg-primary/90">
          Pay Now
        </a>
      </div>

      <div class="rounded-lg border p-5 space-y-2">
        <h2 class="text-base font-semibold tracking-tight">Need Help?</h2>
        <p class="text-sm tracking-tight">Hubungi tim PM One:</p>
        <p class="text-sm tracking-tight">
          <span class="text-muted-foreground">Email:</span> <a :href="`mailto:${reservation.hotel?.contact_email || 'support@pmone.id'}`" class="text-primary hover:underline">{{ reservation.hotel?.contact_email || 'support@pmone.id' }}</a>
        </p>
        <p v-if="reservation.hotel?.contact_phone" class="text-sm tracking-tight">
          <span class="text-muted-foreground">Phone:</span> {{ reservation.hotel.contact_phone }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>

definePageMeta({
  layout: "public",
});

const route = useRoute();
const token = computed(() => route.params.token);

const { data, pending } = await useLazyAsyncData(
  () => `reservation-${token.value}`,
  () => $fetch(`/api/accommodation/reservation/${token.value}`),
);

const reservation = computed(() => data.value?.data);

usePageMeta(null, {
  title: computed(() => `Booking ${reservation.value?.reservation_number ?? ""} · PM One`),
});

const statusClass = computed(() => {
  const map = {
    pending_payment: "bg-warning/15 text-warning-foreground",
    paid: "bg-info/15 text-info-foreground",
    voucher_sent: "bg-success/15 text-success-foreground",
    expired: "bg-muted text-muted-foreground",
    cancelled: "bg-destructive/15 text-destructive",
    refunded: "bg-destructive/15 text-destructive",
  };
  return map[reservation.value?.status] || "bg-muted text-muted-foreground";
});

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);
</script>
