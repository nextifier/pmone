<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-4xl">
    <div class="flex flex-col items-start gap-y-4">
      <ButtonBack :destination="`${eventBase}/reservations`" />
      <div class="flex flex-wrap items-center gap-x-2.5 gap-y-2 min-w-0">
        <h1 class="page-title font-mono text-base sm:text-lg">
          {{ reservation?.reservation_number ?? "Reservation" }}
        </h1>
        <span
          v-if="reservation"
          :class="[
            'inline-flex items-center rounded-full px-2 py-0.5 text-xs sm:text-sm tracking-tight',
            statusBadge,
          ]"
        >
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
                  <Label>Voucher File</Label>
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

      <!-- Pending payment actions: rendered in their own wrapper because the
           voucher/cancel actions block above only mounts for paid/voucher_sent
           statuses and would hide these otherwise. -->
      <div
        v-if="reservation.status === 'pending_payment' && canMarkPaid"
        class="flex flex-wrap gap-2"
      >
        <Button variant="outline" size="sm" @click="markPaidDialogOpen = true">
          <Icon name="hugeicons:money-bag-02" class="size-4 mr-1" />
          Mark as Paid
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
          <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">{{ formatBytes(reservation.voucher.size) }}</p>
        </div>
        <a :href="reservation.voucher.url" target="_blank" rel="noopener" class="text-primary text-sm hover:underline">View</a>
      </div>

      <div class="rounded-md border p-4 space-y-3">
        <h2 class="text-base font-semibold tracking-tight">Guest Information</h2>
        <div class="grid grid-cols-2 gap-3 text-sm tracking-tight">
          <div><p class="text-muted-foreground text-xs sm:text-sm tracking-tight">Name</p>{{ reservation.guest.name }}</div>
          <div><p class="text-muted-foreground text-xs sm:text-sm tracking-tight">Email</p>{{ reservation.guest.email }}</div>
          <div><p class="text-muted-foreground text-xs sm:text-sm tracking-tight">Phone</p>{{ reservation.guest.phone }}</div>
          <div><p class="text-muted-foreground text-xs sm:text-sm tracking-tight">Identity</p>{{ reservation.guest.identity_type_label }}: {{ reservation.guest.identity_number }}</div>
          <div v-if="reservation.guest.company"><p class="text-muted-foreground text-xs sm:text-sm tracking-tight">Company</p>{{ reservation.guest.company }}</div>
          <div v-if="reservation.guest.nationality"><p class="text-muted-foreground text-xs sm:text-sm tracking-tight">Nationality</p>{{ reservation.guest.nationality }}</div>
        </div>
        <div v-if="reservation.special_request" class="text-sm tracking-tight">
          <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">Special Request</p>
          {{ reservation.special_request }}
        </div>
      </div>

      <div class="rounded-md border p-4 space-y-3">
        <h2 class="text-base font-semibold tracking-tight">Booking</h2>
        <p class="text-sm tracking-tight"><span class="text-muted-foreground">Hotel:</span> {{ reservation.hotel?.name }}</p>
        <p v-if="reservation.event" class="text-sm tracking-tight"><span class="text-muted-foreground">Event:</span> {{ reservation.event?.title }}</p>

        <div class="space-y-3">
          <div
            v-for="item in reservation.items"
            :key="item.id"
            class="border-b last:border-b-0 pb-3 last:pb-0"
          >
            <div class="flex flex-wrap items-start justify-between gap-2 text-sm tracking-tight">
              <div class="min-w-0">
                <p class="font-medium">{{ item.room_type?.name }}</p>
                <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
                  {{ item.check_in_date }} → {{ item.check_out_date }} · {{ item.nights }} night{{ item.nights > 1 ? "s" : "" }} · {{ item.qty }} room{{ item.qty > 1 ? "s" : "" }}
                </p>
              </div>
              <p class="font-medium tabular-nums">Rp{{ formatRupiah(item.subtotal) }}</p>
            </div>
            <p
              v-if="item.notes"
              class="text-muted-foreground mt-1 text-xs sm:text-sm tracking-tight italic"
            >
              <span class="font-medium not-italic">Notes:</span> {{ item.notes }}
            </p>
          </div>
        </div>

        <div v-if="reservation.transfers?.length" class="border-t pt-3 space-y-3">
          <p class="text-xs sm:text-sm tracking-tight text-muted-foreground">Transfers</p>
          <div
            v-for="t in reservation.transfers"
            :key="t.id"
            class="space-y-0.5"
          >
            <div class="flex justify-between text-sm tracking-tight">
              <span>{{ t.direction_label }} · {{ t.transfer_date }}</span>
              <span class="tabular-nums">Rp{{ formatRupiah(t.price) }}</span>
            </div>
            <p
              v-if="t.note"
              class="text-muted-foreground text-xs sm:text-sm tracking-tight italic"
            >
              <span class="font-medium not-italic">Notes:</span> {{ t.note }}
            </p>
          </div>
        </div>

        <div class="border-t pt-3 space-y-1.5 text-sm tracking-tight">
          <div class="flex justify-between text-muted-foreground"><span>Subtotal</span><span class="tabular-nums">Rp{{ formatRupiah(reservation.amounts.subtotal_rooms + reservation.amounts.subtotal_transfer) }}</span></div>
          <div v-if="reservation.amounts.surcharge > 0" class="flex justify-between text-muted-foreground"><span>Surcharge</span><span class="tabular-nums">Rp{{ formatRupiah(reservation.amounts.surcharge) }}</span></div>
          <div v-if="reservation.amounts.penalty > 0" class="flex justify-between text-warning-foreground"><span>Penalty</span><span class="tabular-nums">+Rp{{ formatRupiah(reservation.amounts.penalty) }}</span></div>
          <div v-if="reservation.amounts.discount > 0" class="flex justify-between text-success-foreground"><span>Discount</span><span class="tabular-nums">-Rp{{ formatRupiah(reservation.amounts.discount) }}</span></div>
          <div class="flex justify-between text-muted-foreground"><span>Tax</span><span class="tabular-nums">Rp{{ formatRupiah(reservation.amounts.tax) }}</span></div>
          <div v-if="reservation.amounts.service > 0" class="flex justify-between text-muted-foreground"><span>Service</span><span class="tabular-nums">Rp{{ formatRupiah(reservation.amounts.service) }}</span></div>
          <div class="flex justify-between font-semibold pt-1.5 border-t"><span>Total</span><span class="tabular-nums">Rp{{ formatRupiah(reservation.amounts.total) }}</span></div>
        </div>
      </div>

      <!-- Adjustments Section -->
      <div class="rounded-md border p-4 space-y-3">
        <div class="flex items-center justify-between gap-2">
          <h2 class="text-base font-semibold tracking-tight">Adjustments</h2>
          <Button
            v-if="canApplyManual && !reservation.status_label?.toLowerCase().includes('cancelled') && !reservation.status_label?.toLowerCase().includes('refunded')"
            size="sm"
            variant="outline"
            @click="adjustmentDialogOpen = true"
          >
            <Icon name="lucide:plus" class="size-4 shrink-0" />
            Add Adjustment
          </Button>
        </div>

        <div v-if="!reservation.adjustments?.length" class="text-muted-foreground text-sm tracking-tight">
          No adjustments applied.
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="adj in reservation.adjustments"
            :key="adj.id"
            :class="[
              'flex items-center justify-between gap-3 rounded-md border p-3',
              adj.is_voided ? 'opacity-50' : '',
            ]"
          >
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <span
                  :class="[
                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs tracking-tight',
                    adj.kind === 'discount' ? 'bg-success/15 text-success-foreground' : 'bg-warning/15 text-warning-foreground',
                  ]"
                >
                  {{ adj.kind_label }}
                </span>
                <p class="text-sm tracking-tight truncate">{{ adj.label }}</p>
              </div>
              <p class="text-muted-foreground text-xs tracking-tight mt-1">
                {{ adj.value_type === "percentage" ? `${adj.value}%` : `Rp${formatRupiah(adj.value)}` }}
                · applied by {{ adj.applied_by }}
                <span v-if="adj.is_voided"> · voided</span>
              </p>
            </div>
            <div class="text-right shrink-0">
              <p class="text-sm tracking-tight tabular-nums font-medium">
                {{ adj.kind === "discount" ? "-" : "+" }}Rp{{ formatRupiah(adj.amount) }}
              </p>
              <Button
                v-if="!adj.is_voided && canVoid"
                size="sm"
                variant="ghost"
                class="text-destructive h-7 px-2 text-xs"
                @click="voidAdjustment(adj)"
              >
                Void
              </Button>
            </div>
          </div>
        </div>
      </div>

      <!-- Manual Adjustment Dialog -->
      <ManualAdjustmentDialog
        v-model:open="adjustmentDialogOpen"
        target-type="Reservation"
        :target-email="reservation.guest?.email"
        @apply="handleApplyAdjustment"
      />

      <!-- Void Adjustment Confirm Dialog -->
      <DialogResponsive v-model:open="voidDialogOpen" dialog-max-width="26rem">
        <template #default>
          <div class="px-4 pb-10 md:px-6 md:py-5">
            <h3 class="text-lg font-semibold tracking-tight">Void Adjustment</h3>
            <p class="text-muted-foreground text-sm tracking-tight mt-2">
              Void "{{ voidTarget?.label }}"? This will revert promo usage counter (if applicable) and recalculate totals.
            </p>
            <div class="flex justify-end gap-2 pt-4">
              <Button variant="outline" @click="voidDialogOpen = false">Cancel</Button>
              <Button variant="destructive" @click="confirmVoid" :disabled="voiding">
                <Spinner v-if="voiding" />
                {{ voiding ? "Voiding..." : "Void" }}
              </Button>
            </div>
          </div>
        </template>
      </DialogResponsive>

      <div class="rounded-md border p-4 space-y-3 text-sm tracking-tight">
        <h2 class="text-base font-semibold tracking-tight">Payment</h2>
        <div class="flex flex-wrap items-center gap-3">
          <PaymentMethodBadge
            :channel="reservation.payment.channel"
            :method="reservation.payment.method"
          />
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <span class="text-muted-foreground text-xs sm:text-sm tracking-tight">Method:</span>
            {{ reservation.payment.method_label || "-" }}
          </div>
          <div v-if="reservation.payment.destination">
            <span class="text-muted-foreground text-xs sm:text-sm tracking-tight">Destination:</span>
            <span class="font-mono text-xs sm:text-sm tracking-tight">{{ reservation.payment.destination }}</span>
          </div>
          <div v-if="reservation.paid_at">
            <span class="text-muted-foreground text-xs sm:text-sm tracking-tight">Paid at:</span>
            {{ formatDateTime(reservation.paid_at) }}
          </div>
          <div v-if="reservation.payment.xendit_invoice_id">
            <span class="text-muted-foreground text-xs sm:text-sm tracking-tight">Xendit ID:</span>
            <span class="font-mono text-xs sm:text-sm tracking-tight">{{ reservation.payment.xendit_invoice_id }}</span>
          </div>
          <div v-if="reservation.payment.xendit_payment_id">
            <span class="text-muted-foreground text-xs sm:text-sm tracking-tight">Payment ID:</span>
            <span class="font-mono text-xs sm:text-sm tracking-tight">{{ reservation.payment.xendit_payment_id }}</span>
          </div>
          <div v-if="reservation.payment.gateway">
            <span class="text-muted-foreground text-xs sm:text-sm tracking-tight">Gateway:</span>
            {{ reservation.payment.gateway.label || reservation.payment.gateway.provider }}
            <span class="text-muted-foreground text-xs">· {{ reservation.payment.gateway.mode }}</span>
          </div>
        </div>
      </div>

      <div class="rounded-md border p-4 space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-semibold tracking-tight">Activity</h2>
          <button
            type="button"
            class="text-primary text-xs sm:text-sm tracking-tight hover:underline"
            :disabled="loadingActivity"
            @click="loadActivity"
          >
            {{ activity.length ? 'Refresh' : 'Load history' }}
          </button>
        </div>
        <div v-if="loadingActivity" class="text-muted-foreground text-xs sm:text-sm tracking-tight">Loading activity…</div>
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
              <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">{{ formatDateTime(entry.created_at) }}</p>
            </div>
          </div>
        </div>
        <p v-else class="text-muted-foreground text-xs sm:text-sm tracking-tight">No activity recorded yet.</p>
      </div>

      <DialogResponsive v-model:open="cancelDialogOpen" :overflow-content="true" dialog-max-width="28rem">
        <template #default>
          <div class="px-4 pb-10 md:px-6 md:py-5">
            <h3 class="text-lg font-semibold tracking-tight">Cancel & Refund</h3>
            <form @submit.prevent="handleCancel" class="mt-4 space-y-3">
              <div class="space-y-2">
                <Label>Reason</Label>
                <Textarea v-model="cancelForm.reason" rows="3" required />
              </div>
              <div class="space-y-2">
                <Label>Refund Amount (override auto-calc)</Label>
                <Input v-model.number="cancelForm.refund_amount" type="number" min="0" :placeholder="`Auto: Rp${formatRupiah(autoRefund)}`" />
                <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">{{ refundPolicyText }}</p>
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

      <DialogResponsive v-model:open="markPaidDialogOpen" :overflow-content="true" dialog-max-width="28rem">
        <template #default>
          <div class="px-4 pb-10 md:px-6 md:py-5">
            <h3 class="text-lg font-semibold tracking-tight">Mark as Paid</h3>
            <p class="text-muted-foreground mt-1 text-sm tracking-tight">
              Manually confirm payment for this reservation. Use this when payment landed outside Xendit (cash, manual transfer) or when the Xendit webhook did not reach the server.
            </p>
            <form @submit.prevent="handleMarkPaid" class="mt-4 space-y-3">
              <div class="grid grid-cols-2 gap-2">
                <div class="space-y-2">
                  <Label>Payment channel (optional)</Label>
                  <Input v-model="markPaidForm.payment_channel" placeholder="BCA, OVO, Cash..." />
                </div>
                <div class="space-y-2">
                  <Label>Destination/VA (optional)</Label>
                  <Input v-model="markPaidForm.payment_destination" placeholder="0000000000" />
                </div>
              </div>
              <div class="space-y-2">
                <Label>Internal note (optional)</Label>
                <Textarea v-model="markPaidForm.note" rows="2" placeholder="Why this is being marked manually" />
              </div>
              <div class="flex justify-end gap-2 pt-2">
                <Button type="button" variant="outline" @click="markPaidDialogOpen = false">Cancel</Button>
                <Button type="submit" :disabled="markingPaid">
                  <Spinner v-if="markingPaid" />
                  {{ markingPaid ? "Marking..." : "Confirm Payment" }}
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
import { PaymentMethodBadge } from "@/components/ui/payment-method-badge";
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

const { hasPermission } = usePermission();
const canApplyManual = computed(() => hasPermission("promotions.apply_manual"));
const canVoid = computed(() => hasPermission("promotions.void_adjustment"));

usePageMeta(null, {
  title: computed(() => `${reservation.value?.reservation_number ?? "Reservation"} · Reservations`),
});

const adjustmentDialogOpen = ref(false);

const handleApplyAdjustment = async (payload, setErrors) => {
  try {
    const res = await client(
      `/api/events/${props.event.id}/reservations/${ulid.value}/adjustments`,
      { method: "POST", body: payload },
    );
    toast.success(res?.message || "Adjustment applied");
    adjustmentDialogOpen.value = false;
    await refresh();
  } catch (err) {
    if (err.response?.status === 422) {
      const errors = err.response._data?.errors || {};
      setErrors?.(errors);
      toast.error(err.response._data?.message || "Validation failed");
    } else {
      toast.error("Failed to apply adjustment", { description: err?.data?.message });
    }
  }
};

const voidTarget = ref(null);
const voidDialogOpen = ref(false);
const voiding = ref(false);

const voidAdjustment = (adj) => {
  voidTarget.value = adj;
  voidDialogOpen.value = true;
};

const confirmVoid = async () => {
  if (!voidTarget.value) return;
  voiding.value = true;
  try {
    await client(
      `/api/events/${props.event.id}/reservations/${ulid.value}/adjustments/${voidTarget.value.ulid}`,
      { method: "DELETE" },
    );
    toast.success("Adjustment voided");
    voidDialogOpen.value = false;
    voidTarget.value = null;
    await refresh();
  } catch (err) {
    toast.error("Failed to void", { description: err?.data?.message });
  } finally {
    voiding.value = false;
  }
};

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

const canMarkPaid = computed(() => hasPermission("reservations.mark_paid"));
const markPaidDialogOpen = ref(false);
const markingPaid = ref(false);
const markPaidForm = reactive({
  payment_channel: "",
  payment_destination: "",
  note: "",
});

const handleMarkPaid = async () => {
  markingPaid.value = true;
  try {
    await client(`/api/events/${props.event.id}/reservations/${ulid.value}/mark-paid`, {
      method: "POST",
      body: {
        payment_channel: markPaidForm.payment_channel || null,
        payment_destination: markPaidForm.payment_destination || null,
        note: markPaidForm.note || null,
      },
    });
    toast.success("Reservation marked as paid");
    markPaidDialogOpen.value = false;
    markPaidForm.payment_channel = "";
    markPaidForm.payment_destination = "";
    markPaidForm.note = "";
    await refresh();
  } catch (err) {
    toast.error("Mark paid failed", { description: err?.data?.message || err?.message });
  } finally {
    markingPaid.value = false;
  }
};

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
  if ("total_amount" in changes) return `Total updated to Rp${formatRupiah(changes.total_amount)}`;
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
