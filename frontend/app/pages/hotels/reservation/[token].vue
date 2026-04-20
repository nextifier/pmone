<template>
  <div class="mx-auto max-w-3xl space-y-6 px-4 pt-4 pb-16">
    <div v-if="pending" class="space-y-6">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-2">
          <Skeleton class="h-8 w-72" />
          <Skeleton class="h-4 w-48" />
        </div>
        <Skeleton class="h-6 w-28 rounded-full" />
      </div>
      <Skeleton class="h-36 w-full" />
      <Skeleton class="h-48 w-full" />
      <Skeleton class="h-32 w-full" />
    </div>

    <div
      v-else-if="!reservation"
      class="text-muted-foreground rounded-md border border-dashed py-12 text-center text-sm tracking-tight"
    >
      Reservation not found.
    </div>

    <div v-else class="space-y-6">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-1">
          <h1 class="page-title">Booking {{ reservation.reservation_number }}</h1>
          <p class="text-muted-foreground text-sm tracking-tight">{{ reservation.hotel?.name }}</p>
        </div>
        <span
          :class="[
            'inline-flex items-center rounded-full px-3 py-1 text-xs tracking-tight sm:text-sm',
            statusClass,
          ]"
        >
          {{ reservation.status_label }}
        </span>
      </div>

      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Guest Information</div>
        </div>
        <div class="frame-panel">
          <div class="grid grid-cols-1 gap-4 text-sm tracking-tight sm:grid-cols-2">
            <div>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Name</p>
              <p>{{ reservation.guest.name }}</p>
            </div>
            <div>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Email</p>
              <p>{{ reservation.guest.email }}</p>
            </div>
            <div>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Phone</p>
              <p>{{ reservation.guest.phone }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Booking Details</div>
        </div>
        <div class="frame-panel space-y-3">
          <div
            v-for="item in reservation.items"
            :key="item.room_type_name"
            class="border-b pb-2 last:border-b-0 last:pb-0"
          >
            <div class="flex justify-between gap-3 text-sm tracking-tight">
              <div>
                <p class="font-medium">{{ item.room_type_name }}</p>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  {{ item.check_in_date }} → {{ item.check_out_date }} · {{ item.nights }} night(s)
                  · {{ item.qty }} room(s)
                </p>
              </div>
              <p class="font-medium tabular-nums">Rp {{ formatRupiah(item.subtotal) }}</p>
            </div>
          </div>

          <div v-if="reservation.transfers?.length" class="space-y-2 border-t pt-3">
            <h3 class="text-sm font-medium tracking-tight">Transfer</h3>
            <div
              v-for="(t, idx) in reservation.transfers"
              :key="idx"
              class="flex justify-between gap-3 text-sm tracking-tight"
            >
              <div>
                <p>{{ t.direction }} · {{ t.transfer_date }}</p>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  {{ t.pickup_location || "-" }} → {{ t.dropoff_location || "-" }}
                </p>
              </div>
              <p class="tabular-nums">Rp {{ formatRupiah(t.price) }}</p>
            </div>
          </div>

          <div class="space-y-1.5 border-t pt-3 text-sm tracking-tight">
            <div class="text-muted-foreground flex justify-between">
              <span>Subtotal</span>
              <span class="tabular-nums">
                Rp
                {{
                  formatRupiah(
                    reservation.amounts.subtotal_rooms + reservation.amounts.subtotal_transfer
                  )
                }}
              </span>
            </div>
            <div class="text-muted-foreground flex justify-between">
              <span>Tax</span>
              <span class="tabular-nums">Rp {{ formatRupiah(reservation.amounts.tax) }}</span>
            </div>
            <div
              v-if="reservation.amounts.service > 0"
              class="text-muted-foreground flex justify-between"
            >
              <span>Service</span>
              <span class="tabular-nums">Rp {{ formatRupiah(reservation.amounts.service) }}</span>
            </div>
            <div class="flex justify-between border-t pt-1.5 text-base font-semibold">
              <span>Total</span>
              <span class="tabular-nums">Rp {{ formatRupiah(reservation.amounts.total) }}</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="reservation.status === 'pending_payment' && reservation.payment_url" class="frame">
        <div class="frame-header">
          <div class="frame-title">Payment Pending</div>
        </div>
        <div class="frame-panel space-y-3">
          <p class="text-sm tracking-tight">
            Payment has not been received yet. Please continue via Xendit:
          </p>
          <p v-if="expiresAtLabel" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            Payment link expires on {{ expiresAtLabel }}.
          </p>
          <a
            :href="reservation.payment_url"
            class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center rounded-md px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
          >
            Pay Now
          </a>
        </div>
      </div>

      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Documents</div>
        </div>
        <div class="frame-panel flex flex-wrap gap-2">
          <a
            :href="invoicePdfUrl"
            target="_blank"
            rel="noopener"
            class="border-border hover:bg-muted rounded-md border px-3 py-1.5 text-sm tracking-tight active:scale-98"
          >
            Download Invoice
          </a>
          <a
            v-if="isPaid"
            :href="receiptPdfUrl"
            target="_blank"
            rel="noopener"
            class="border-border hover:bg-muted rounded-md border px-3 py-1.5 text-sm tracking-tight active:scale-98"
          >
            Download Receipt
          </a>
        </div>
      </div>

      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Need Help?</div>
        </div>
        <div class="frame-panel space-y-2">
          <p class="text-sm tracking-tight">Contact PM One support:</p>
          <p class="text-sm tracking-tight">
            <span class="text-muted-foreground">Email:</span>
            <a
              :href="`mailto:${reservation.hotel?.contact_email || 'support@pmone.id'}`"
              class="text-primary ml-1 underline"
            >
              {{ reservation.hotel?.contact_email || "support@pmone.id" }}
            </a>
          </p>
          <p v-if="reservation.hotel?.contact_phone" class="text-sm tracking-tight">
            <span class="text-muted-foreground">Phone:</span>
            {{ reservation.hotel.contact_phone }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Skeleton } from "@/components/ui/skeleton";

definePageMeta({
  layout: "default",
});

const route = useRoute();
const token = computed(() => route.params.token);

const { data, pending } = await useLazyAsyncData(
  () => `reservation-${token.value}`,
  () => $fetch(`/api/accommodation/reservation/${token.value}`)
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

const isPaid = computed(() => ["paid", "voucher_sent"].includes(reservation.value?.status));

const invoicePdfUrl = computed(() => `/api/accommodation/reservation/${token.value}/invoice.pdf`);
const receiptPdfUrl = computed(() => `/api/accommodation/reservation/${token.value}/receipt.pdf`);

const expiresAtLabel = computed(() => {
  const iso = reservation.value?.payment_expires_at;
  if (!iso) return null;
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return null;
  return date.toLocaleString("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
});

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);
</script>
