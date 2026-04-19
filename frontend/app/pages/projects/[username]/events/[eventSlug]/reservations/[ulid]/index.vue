<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-4xl">
    <div class="flex items-center justify-between gap-2">
      <div class="flex items-center gap-x-2.5 min-w-0">
        <NuxtLink :to="`${eventBase}/reservations`" class="hover:bg-muted text-muted-foreground inline-flex size-8 items-center justify-center rounded-md shrink-0">
          <Icon name="lucide:arrow-left" class="size-4" />
        </NuxtLink>
        <h1 class="page-title font-mono text-base sm:text-lg">{{ reservation?.reservation_number ?? "Reservation" }}</h1>
        <span v-if="reservation" :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs sm:text-sm tracking-tight', statusBadge]">
          {{ reservation.status_label }}
        </span>
      </div>
    </div>

    <div v-if="pending" class="flex justify-center py-10">
      <Spinner class="size-6" />
    </div>

    <div v-else-if="reservation" class="space-y-6">
      <div v-if="['paid', 'voucher_sent'].includes(reservation.status)" class="flex flex-wrap gap-2">
        <DialogResponsive v-model:open="voucherDialogOpen" :overflow-content="true" dialog-max-width="28rem">
          <template #trigger="{ open }">
            <Button v-if="reservation.can_upload_voucher" variant="outline" size="sm" @click="open()">
              <Icon name="lucide:upload" class="size-4 mr-1" />
              {{ reservation.voucher ? "Replace Voucher" : "Upload Voucher" }}
            </Button>
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <h3 class="text-lg font-semibold tracking-tight">Upload Voucher</h3>
              <p class="text-muted-foreground text-sm tracking-tight mt-1">PDF, JPG, or PNG. Max 20MB.</p>
              <form @submit.prevent="handleVoucherUpload" class="mt-4 space-y-4">
                <div class="space-y-2">
                  <Label>Voucher File<span class="text-destructive">*</span></Label>
                  <InputFile
                    v-model="voucherFiles"
                    :max-file-size="'20MB'"
                    :accepted-file-types="['application/pdf', 'image/jpeg', 'image/png']"
                  />
                </div>
                <div class="flex justify-end gap-2">
                  <Button type="button" variant="outline" @click="voucherDialogOpen = false">Cancel</Button>
                  <Button type="submit" :disabled="uploading || !voucherFiles.length">
                    <Spinner v-if="uploading" />
                    {{ uploading ? "Uploading..." : "Upload" }}
                  </Button>
                </div>
              </form>
            </div>
          </template>
        </DialogResponsive>

        <Button
          v-if="reservation.can_send_voucher"
          variant="outline"
          size="sm"
          :disabled="!reservation.voucher || sendingVoucher"
          @click="handleSendVoucher"
        >
          <Icon v-if="sendingVoucher" name="svg-spinners:ring-resize" class="size-4 mr-1" />
          <Icon v-else name="lucide:send" class="size-4 mr-1" />
          {{ reservation.status === "voucher_sent" ? "Resend Voucher" : "Send Voucher" }}
        </Button>

        <Button v-if="reservation.can_cancel" variant="outline" size="sm" @click="cancelDialogOpen = true" class="text-destructive">
          <Icon name="lucide:x" class="size-4 mr-1" />
          Cancel & Refund
        </Button>
      </div>

      <div v-if="reservation.can_view_documents" class="rounded-md border p-4 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium tracking-tight">Documents</p>
          <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">Invoice & Receipt PDF</p>
        </div>
        <a :href="`${apiBase}/api/events/${event.id}/reservations/${reservation.ulid}/invoice.pdf`" target="_blank" class="border-border hover:bg-muted rounded-md border px-3 py-1.5 text-sm tracking-tight">
          Invoice PDF
        </a>
        <a
          v-if="['paid', 'voucher_sent'].includes(reservation.status)"
          :href="`${apiBase}/api/events/${event.id}/reservations/${reservation.ulid}/receipt.pdf`"
          target="_blank"
          class="border-border hover:bg-muted rounded-md border px-3 py-1.5 text-sm tracking-tight"
        >
          Receipt PDF
        </a>
      </div>

      <div v-if="reservation.voucher" class="rounded-md border p-4 flex items-center gap-3">
        <Icon name="lucide:file" class="size-5 text-muted-foreground shrink-0" />
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium tracking-tight truncate">{{ reservation.voucher.name }}</p>
          <p class="text-muted-foreground text-xs">{{ formatBytes(reservation.voucher.size) }}</p>
        </div>
        <a :href="reservation.voucher.url" target="_blank" class="text-primary text-sm hover:underline">View</a>
      </div>

      <div class="rounded-md border p-4 space-y-3">
        <h2 class="text-base font-semibold tracking-tight">Guest Information</h2>
        <div class="grid grid-cols-2 gap-3 text-sm tracking-tight">
          <div><p class="text-muted-foreground text-xs">Name</p>{{ reservation.guest.name }}</div>
          <div><p class="text-muted-foreground text-xs">Email</p>{{ reservation.guest.email }}</div>
          <div><p class="text-muted-foreground text-xs">Phone</p>{{ reservation.guest.phone }}</div>
          <div><p class="text-muted-foreground text-xs">Identity</p>{{ reservation.guest.identity_type_label }}: {{ reservation.guest.identity_number }}</div>
          <div v-if="reservation.guest.company"><p class="text-muted-foreground text-xs">Company</p>{{ reservation.guest.company }}</div>
          <div v-if="reservation.guest.nationality"><p class="text-muted-foreground text-xs">Nationality</p>{{ reservation.guest.nationality }}</div>
        </div>
        <div v-if="reservation.special_request" class="text-sm tracking-tight">
          <p class="text-muted-foreground text-xs">Special Request</p>
          {{ reservation.special_request }}
        </div>
      </div>

      <div class="rounded-md border p-4 space-y-3">
        <h2 class="text-base font-semibold tracking-tight">Booking</h2>
        <p class="text-sm tracking-tight"><span class="text-muted-foreground">Hotel:</span> {{ reservation.hotel?.name }}</p>
        <p v-if="reservation.event" class="text-sm tracking-tight"><span class="text-muted-foreground">Event:</span> {{ reservation.event?.title }}</p>

        <table class="w-full text-sm tracking-tight">
          <thead class="text-muted-foreground text-xs">
            <tr class="text-left border-b"><th class="py-1">Room</th><th class="py-1">Dates</th><th class="text-right py-1">Qty</th><th class="text-right py-1">Subtotal</th></tr>
          </thead>
          <tbody>
            <tr v-for="item in reservation.items" :key="item.id" class="border-b last:border-b-0">
              <td class="py-1.5">{{ item.room_type?.name }}</td>
              <td class="py-1.5">{{ item.check_in_date }} - {{ item.check_out_date }} ({{ item.nights }}n)</td>
              <td class="py-1.5 text-right">{{ item.qty }}</td>
              <td class="py-1.5 text-right tabular-nums">Rp {{ formatRupiah(item.subtotal) }}</td>
            </tr>
          </tbody>
        </table>

        <div v-if="reservation.transfers?.length" class="border-t pt-2">
          <p class="text-xs text-muted-foreground mb-1">Transfers</p>
          <div v-for="t in reservation.transfers" :key="t.id" class="flex justify-between text-sm tracking-tight py-0.5">
            <span>{{ t.direction_label }} - {{ t.transfer_date }}</span>
            <span class="tabular-nums">Rp {{ formatRupiah(t.price) }}</span>
          </div>
        </div>

        <div class="border-t pt-3 space-y-1.5 text-sm tracking-tight">
          <div class="flex justify-between text-muted-foreground"><span>Subtotal</span><span class="tabular-nums">Rp {{ formatRupiah(reservation.amounts.subtotal_rooms + reservation.amounts.subtotal_transfer) }}</span></div>
          <div class="flex justify-between text-muted-foreground"><span>Tax</span><span class="tabular-nums">Rp {{ formatRupiah(reservation.amounts.tax) }}</span></div>
          <div v-if="reservation.amounts.service > 0" class="flex justify-between text-muted-foreground"><span>Service</span><span class="tabular-nums">Rp {{ formatRupiah(reservation.amounts.service) }}</span></div>
          <div class="flex justify-between font-semibold pt-1.5 border-t"><span>Total</span><span class="tabular-nums">Rp {{ formatRupiah(reservation.amounts.total) }}</span></div>
        </div>
      </div>

      <div class="rounded-md border p-4 space-y-2 text-sm tracking-tight">
        <h2 class="text-base font-semibold tracking-tight">Payment</h2>
        <div class="grid grid-cols-2 gap-2">
          <div><span class="text-muted-foreground text-xs">Method:</span> {{ reservation.payment.method_label || "-" }}</div>
          <div v-if="reservation.paid_at"><span class="text-muted-foreground text-xs">Paid at:</span> {{ formatDateTime(reservation.paid_at) }}</div>
          <div v-if="reservation.payment.xendit_invoice_id"><span class="text-muted-foreground text-xs">Xendit ID:</span> <span class="font-mono text-xs">{{ reservation.payment.xendit_invoice_id }}</span></div>
        </div>
      </div>

      <div class="rounded-md border p-4 space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-semibold tracking-tight">Activity</h2>
          <button
            type="button"
            class="text-primary text-xs hover:underline"
            :disabled="loadingActivity"
            @click="loadActivity"
          >
            {{ activity.length ? 'Refresh' : 'Load history' }}
          </button>
        </div>
        <div v-if="loadingActivity" class="text-muted-foreground text-xs tracking-tight">Loading activity…</div>
        <div v-else-if="activity.length" class="space-y-2">
          <div
            v-for="entry in activity"
            :key="entry.id"
            class="flex gap-3 items-start pb-2 border-b last:border-b-0 last:pb-0"
          >
            <div class="size-2 rounded-full mt-1.5 shrink-0" :class="activityDotClass(entry)"></div>
            <div class="flex-1 min-w-0">
              <p class="text-xs sm:text-sm tracking-tight">
                <span class="font-medium">{{ activityLabel(entry) }}</span>
                <span v-if="entry.causer" class="text-muted-foreground"> by {{ entry.causer.name }}</span>
              </p>
              <p class="text-muted-foreground text-xs tracking-tight">{{ formatDateTime(entry.created_at) }}</p>
            </div>
          </div>
        </div>
        <p v-else class="text-muted-foreground text-xs tracking-tight">No activity recorded yet.</p>
      </div>

      <DialogResponsive v-model:open="cancelDialogOpen" :overflow-content="true" dialog-max-width="28rem">
        <template #default>
          <div class="px-4 pb-10 md:px-6 md:py-5">
            <h3 class="text-lg font-semibold tracking-tight">Cancel & Refund</h3>
            <form @submit.prevent="handleCancel" class="mt-4 space-y-3">
              <div class="space-y-2">
                <Label>Reason<span class="text-destructive">*</span></Label>
                <Textarea v-model="cancelForm.reason" rows="3" required />
              </div>
              <div class="space-y-2">
                <Label>Refund Amount (override auto-calc)</Label>
                <Input v-model.number="cancelForm.refund_amount" type="number" min="0" :placeholder="`Auto: Rp ${formatRupiah(autoRefund)}`" />
                <p class="text-muted-foreground text-xs">{{ refundPolicyText }}</p>
              </div>
              <label class="flex items-center gap-2 text-sm tracking-tight">
                <Checkbox v-model="cancelForm.process_refund" />
                <span>Process Xendit refund automatically</span>
              </label>
              <div class="flex justify-end gap-2 pt-2">
                <Button type="button" variant="outline" @click="cancelDialogOpen = false">Cancel</Button>
                <Button type="submit" variant="destructive" :disabled="cancelling">
                  <Spinner v-if="cancelling" />
                  {{ cancelling ? "Processing..." : "Confirm Cancellation" }}
                </Button>
              </div>
            </form>
          </div>
        </template>
      </DialogResponsive>
    </div>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import InputFile from "@/components/InputFile.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { Textarea } from "@/components/ui/textarea";
import { computed, reactive, ref } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["reservations.read"],
  layout: "app",
});

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();
const ulid = computed(() => route.params.ulid);
const client = useSanctumClient();
const config = useRuntimeConfig();
const apiBase = config.public.apiUrl;

const eventBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}`
);

const { data, pending, refresh } = await useLazySanctumFetch(
  () => `/api/events/${props.event?.id}/reservations/${ulid.value}`,
  { key: () => `reservation-${props.event?.id}-${ulid.value}` }
);

const reservation = computed(() => data.value?.data);

usePageMeta(null, {
  title: computed(() => `${reservation.value?.reservation_number ?? "Reservation"} · Reservations`),
});

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);
const formatDateTime = (iso) => iso ? new Date(iso).toLocaleString("id-ID") : "-";
const formatBytes = (b) => b > 1024 * 1024 ? `${(b / (1024 * 1024)).toFixed(1)} MB` : `${Math.round(b / 1024)} KB`;

const statusBadge = computed(() => {
  const map = {
    pending_payment: "bg-warning/15 text-warning-foreground",
    paid: "bg-info/15 text-info-foreground",
    voucher_sent: "bg-success/15 text-success-foreground",
    expired: "bg-muted text-muted-foreground",
    cancelled: "bg-destructive/15 text-destructive",
    refunded: "bg-muted text-muted-foreground border border-destructive/30",
  };
  return map[reservation.value?.status] || "bg-muted text-muted-foreground";
});

const voucherDialogOpen = ref(false);
const voucherFiles = ref([]);
const uploading = ref(false);

const handleVoucherUpload = async () => {
  const tmpVoucher = voucherFiles.value?.[0];
  if (!tmpVoucher || !String(tmpVoucher).startsWith("tmp-")) return;
  uploading.value = true;
  try {
    await client(`/api/events/${props.event.id}/reservations/${ulid.value}/voucher`, {
      method: "POST",
      body: { tmp_voucher: tmpVoucher },
    });
    toast.success("Voucher uploaded");
    voucherDialogOpen.value = false;
    voucherFiles.value = [];
    await refresh();
  } catch (err) {
    toast.error("Upload failed", { description: err?.data?.message || err?.message });
  } finally {
    uploading.value = false;
  }
};

const sendingVoucher = ref(false);
const handleSendVoucher = async () => {
  sendingVoucher.value = true;
  try {
    await client(`/api/events/${props.event.id}/reservations/${ulid.value}/send-voucher`, { method: "POST" });
    toast.success("Voucher email queued");
    await refresh();
  } catch (err) {
    toast.error("Send failed", { description: err?.data?.message || err?.message });
  } finally {
    sendingVoucher.value = false;
  }
};

const cancelDialogOpen = ref(false);
const cancelling = ref(false);
const cancelForm = reactive({
  reason: "",
  refund_amount: null,
  process_refund: true,
});

const earliestCheckIn = computed(() => {
  const items = reservation.value?.items ?? [];
  if (!items.length) return null;
  return items
    .map((i) => i.check_in_date)
    .filter(Boolean)
    .sort()[0];
});

const daysUntilCheckIn = computed(() => {
  if (!earliestCheckIn.value) return null;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const checkIn = new Date(earliestCheckIn.value);
  checkIn.setHours(0, 0, 0, 0);
  if (Number.isNaN(checkIn.getTime())) return null;
  return Math.round((checkIn.getTime() - today.getTime()) / 86400000);
});

const autoRefund = computed(() => {
  const total = Number(reservation.value?.amounts?.total) || 0;
  const days = daysUntilCheckIn.value;
  if (days === null) return 0;
  if (days >= 14) return Math.round(total * 100) / 100;
  if (days >= 7) return Math.round(total * 50) / 100;
  return 0;
});

const refundPolicyText = computed(() => {
  const days = daysUntilCheckIn.value;
  if (days === null) return "H-14: 100%, H-7 to H-13: 50%, H<7: 0%";
  if (days >= 14) return `H-${days}: 100% refund`;
  if (days >= 7) return `H-${days}: 50% refund`;
  if (days >= 0) return `H-${days}: no refund (within 7 days of check-in)`;
  return "Check-in date has passed: no refund";
});

const handleCancel = async () => {
  cancelling.value = true;
  try {
    const payload = {
      reason: cancelForm.reason,
      process_refund: cancelForm.process_refund,
    };
    if (cancelForm.refund_amount !== null && cancelForm.refund_amount !== "") {
      payload.refund_amount = cancelForm.refund_amount;
    }
    await client(`/api/events/${props.event.id}/reservations/${ulid.value}/cancel`, { method: "POST", body: payload });
    toast.success("Reservation cancelled");
    cancelDialogOpen.value = false;
    await refresh();
    await loadActivity();
  } catch (err) {
    toast.error("Cancel failed", { description: err?.data?.message || err?.message });
  } finally {
    cancelling.value = false;
  }
};

const activity = ref([]);
const loadingActivity = ref(false);

const loadActivity = async () => {
  loadingActivity.value = true;
  try {
    const res = await client(
      `/api/events/${props.event.id}/reservations/${ulid.value}/activity`
    );
    activity.value = res?.data ?? [];
  } catch (err) {
    toast.error("Could not load activity", { description: err?.data?.message || err?.message });
  } finally {
    loadingActivity.value = false;
  }
};

const activityLabel = (entry) => {
  const changes = entry.changes ?? {};
  if ("status" in changes) {
    return `Status changed to ${changes.status}`;
  }
  if ("paid_at" in changes) return "Marked as paid";
  if ("cancelled_at" in changes) return "Cancelled";
  if ("refunded_at" in changes) return "Refunded";
  if ("voucher_sent_at" in changes) return "Voucher sent";
  if ("total_amount" in changes) return `Total updated to Rp ${formatRupiah(changes.total_amount)}`;
  return entry.description || "Updated";
};

const activityDotClass = (entry) => {
  const changes = entry.changes ?? {};
  if (changes.status === "cancelled" || changes.status === "refunded") return "bg-destructive";
  if (changes.status === "paid" || "paid_at" in changes) return "bg-success";
  if (changes.status === "voucher_sent") return "bg-info";
  if (changes.status === "expired") return "bg-muted-foreground";
  return "bg-muted-foreground/50";
};
</script>
