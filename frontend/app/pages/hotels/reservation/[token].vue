<template>
  <div class="mx-auto max-w-3xl space-y-6 px-4 pt-4 pb-16">
    <div v-if="pending" class="space-y-6">
      <Skeleton class="h-9 w-24 rounded-md" />
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-2">
          <Skeleton class="h-8 w-72 sm:h-9" />
          <Skeleton class="h-4 w-48" />
        </div>
        <Skeleton class="h-6 w-28 rounded-full" />
      </div>
      <Skeleton class="h-36 w-full rounded-xl" />
      <Skeleton class="h-56 w-full rounded-xl" />
      <Skeleton class="h-28 w-full rounded-xl" />
      <Skeleton class="h-32 w-full rounded-xl" />
    </div>

    <div
      v-else-if="!reservation"
      class="text-muted-foreground rounded-md border border-dashed py-12 text-center text-sm tracking-tight"
    >
      Reservation not found.
    </div>

    <div v-else class="space-y-6">
      <ButtonBack destination="/hotels" />
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-1">
          <h1 class="page-title">Booking {{ reservation.reservation_number }}</h1>
          <p class="text-muted-foreground text-sm tracking-tight">{{ reservation.hotel?.name }}</p>
        </div>
        <Badge :variant="statusVariant" with-icon plain>
          {{ reservation.status_label }}
        </Badge>
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
            v-for="(item, idx) in reservation.items"
            :key="idx"
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
              <p class="font-medium tabular-nums">Rp{{ formatRupiah(item.subtotal) }}</p>
            </div>
            <p
              v-if="item.notes"
              class="text-muted-foreground mt-1 text-xs tracking-tight italic sm:text-sm"
            >
              <span class="font-medium not-italic">Notes:</span> {{ item.notes }}
            </p>
          </div>

          <div v-if="reservation.transfers?.length" class="space-y-2 border-t pt-3">
            <h3 class="text-sm font-medium tracking-tight">Transfer</h3>
            <div v-for="(t, idx) in reservation.transfers" :key="idx" class="space-y-0.5">
              <div class="flex justify-between gap-3 text-sm tracking-tight">
                <div>
                  <p>{{ t.direction_label || t.direction }} · {{ t.transfer_date }}</p>
                  <p
                    v-if="t.pickup_location || t.dropoff_location"
                    class="text-muted-foreground text-xs tracking-tight sm:text-sm"
                  >
                    {{ t.pickup_location || "-" }} → {{ t.dropoff_location || "-" }}
                  </p>
                </div>
                <p class="tabular-nums">Rp{{ formatRupiah(t.price) }}</p>
              </div>
              <p
                v-if="t.note"
                class="text-muted-foreground text-xs tracking-tight italic sm:text-sm"
              >
                <span class="font-medium not-italic">Notes:</span> {{ t.note }}
              </p>
            </div>
          </div>

          <div
            v-if="reservation.special_request"
            class="border-t pt-3 text-sm tracking-tight"
          >
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">Special Request</p>
            <p class="italic">{{ reservation.special_request }}</p>
          </div>

          <div class="space-y-1.5 border-t pt-3 text-sm tracking-tight">
            <div class="text-muted-foreground flex justify-between">
              <span>Subtotal</span>
              <span class="tabular-nums">Rp{{
                formatRupiah(
                  reservation.amounts.subtotal_rooms + reservation.amounts.subtotal_transfer
                )
              }}</span>
            </div>
            <div class="text-muted-foreground flex justify-between">
              <span>Tax</span>
              <span class="tabular-nums">Rp{{ formatRupiah(reservation.amounts.tax) }}</span>
            </div>
            <div
              v-if="reservation.amounts.service > 0"
              class="text-muted-foreground flex justify-between"
            >
              <span>Service</span>
              <span class="tabular-nums">Rp{{ formatRupiah(reservation.amounts.service) }}</span>
            </div>
            <div class="flex justify-between border-t pt-1.5 text-base font-semibold">
              <span>Total</span>
              <span class="tabular-nums">Rp{{ formatRupiah(reservation.amounts.total) }}</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="reservation.status === 'pending_payment'" class="frame">
        <div class="frame-header">
          <div class="frame-title">Payment Pending</div>
        </div>
        <div class="frame-panel space-y-3">
          <template v-if="reservation.payment_url">
            <p class="text-sm tracking-tight">
              Payment has not been received yet. Please continue to checkout:
            </p>
            <p v-if="expiresAtLabel" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Payment link expires on {{ expiresAtLabel }}.
            </p>
            <Button as-child>
              <a :href="reservation.payment_url">Pay Now</a>
            </Button>
          </template>
          <template v-else>
            <div
              class="border-warning/40 bg-warning/10 flex items-start gap-3 rounded-md border p-3"
            >
              <Icon
                name="hugeicons:alert-circle"
                class="text-warning-foreground mt-0.5 size-4 shrink-0"
              />
              <div class="flex-1 text-sm tracking-tight">
                <p class="text-warning-foreground font-medium">Payment link unavailable</p>
                <p class="text-muted-foreground mt-1 text-xs tracking-tight sm:text-sm">
                  We couldn't generate your payment link automatically. Click below to retry. Your
                  booking is held - no need to start over.
                </p>
              </div>
            </div>
            <Button :disabled="retrying" @click="onRetryPayment">
              <Spinner v-if="retrying" class="size-4" />
              <Icon v-else name="hugeicons:reload" class="size-4 shrink-0" />
              {{ retrying ? "Generating payment link..." : "Retry payment link" }}
            </Button>
            <p v-if="retryError" class="text-destructive text-xs tracking-tight sm:text-sm">
              {{ retryError }}
            </p>
          </template>
        </div>
      </div>

      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Documents</div>
        </div>
        <div class="frame-panel">
          <div class="flex flex-wrap items-center justify-center gap-2">
            <Button as-child variant="outline" size="sm">
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
        </div>
      </div>

      <div v-if="reservation.hotel?.cancellation_policy" class="frame">
        <div class="frame-header">
          <div class="frame-title">Cancellation Policy</div>
        </div>
        <div class="frame-panel">
          <p class="text-body text-sm whitespace-pre-line tracking-tight">
            {{ reservation.hotel.cancellation_policy }}
          </p>
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
import { Badge } from "../../../components/ui/badge";
import { Button } from "../../../components/ui/button";
import { Skeleton } from "../../../components/ui/skeleton";
import { Spinner } from "../../../components/ui/spinner";

definePageMeta({
  layout: "default",
  noFooter: true,
});

const route = useRoute();
const token = computed(() => route.params.token);

const { data, pending, refresh } = await useLazyAsyncData(
  () => `reservation-${token.value}`,
  () => $fetch(`/api/hotels/reservation/${token.value}`)
);

const reservation = computed(() => data.value?.data);

const retrying = ref(false);
const retryError = ref("");

const onRetryPayment = async () => {
  retrying.value = true;
  retryError.value = "";
  try {
    const res = await $fetch(`/api/hotels/reservation/${token.value}/retry-payment`, {
      method: "POST",
    });
    await refresh();
    const url = res?.data?.payment_url;
    if (url) {
      window.location.href = url;
    }
  } catch (err) {
    retryError.value =
      err?.data?.message ||
      err?.statusMessage ||
      "Could not generate payment link. Please try again in a moment.";
  } finally {
    retrying.value = false;
  }
};

usePageMeta(null, {
  title: computed(() => `Booking ${reservation.value?.reservation_number ?? ""}`),
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

const isPaid = computed(() => ["paid", "voucher_sent"].includes(reservation.value?.status));

const invoicePdfUrl = computed(() => `/api/hotels/reservation/${token.value}/invoice.pdf`);
const receiptPdfUrl = computed(() => `/api/hotels/reservation/${token.value}/receipt.pdf`);

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
