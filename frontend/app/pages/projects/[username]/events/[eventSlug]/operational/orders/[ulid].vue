<template>
  <div class="flex flex-col gap-y-6">
    <!-- Back Button -->
    <div>
      <NuxtLink
        :to="`/projects/${route.params.username}/events/${route.params.eventSlug}/operational/orders`"
        class="text-muted-foreground hover:text-foreground inline-flex items-center gap-x-1.5 text-sm tracking-tight transition"
      >
        <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
        <span>Back to Orders</span>
      </NuxtLink>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16">
      <div class="flex items-center gap-x-2">
        <Spinner class="size-4 shrink-0" />
        <span class="text-sm tracking-tight">Loading order...</span>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="!order" class="flex flex-col items-center justify-center py-16 text-center">
      <Icon name="hugeicons:alert-circle" class="text-muted-foreground size-10" />
      <h4 class="mt-3 font-semibold tracking-tight">Order not found</h4>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">
        This order could not be loaded.
      </p>
    </div>

    <!-- Content -->
    <template v-else>
      <!-- Order Header -->
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
          <div class="flex items-center gap-x-2.5">
            <h3 class="font-mono text-lg font-semibold tracking-tight">
              {{ order.order_number }}
            </h3>
            <span
              class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
              :class="statusBadgeClass(order.operational_status)"
            >
              {{ order.operational_status_label || order.operational_status }}
            </span>
          </div>
          <p class="text-muted-foreground text-sm tracking-tight">
            Submitted {{ formatDate(order.submitted_at || order.created_at) }}
          </p>
        </div>

        <!-- Status Update -->
        <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
          <div class="flex w-full items-center gap-x-2 sm:w-auto">
            <span class="text-muted-foreground grow text-right text-sm tracking-tight"
              >Operational:</span
            >
            <Select
              :model-value="order.operational_status"
              :disabled="statusLoading"
              @update:model-value="handleOperationalStatusUpdate"
            >
              <SelectTrigger class="w-40">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="submitted">Submitted</SelectItem>
                <SelectItem value="confirmed">Confirmed</SelectItem>
                <SelectItem value="processing">Processing</SelectItem>
                <SelectItem value="completed">Completed</SelectItem>
                <SelectItem value="cancelled">Cancelled</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="flex w-full items-center gap-x-2 sm:w-auto">
            <span class="text-muted-foreground grow text-right text-sm tracking-tight"
              >Payment:</span
            >
            <Select
              :model-value="order.payment_status"
              :disabled="paymentStatusLoading"
              @update:model-value="handlePaymentStatusUpdate"
            >
              <SelectTrigger class="w-40">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="not_invoiced">Not Invoiced</SelectItem>
                <SelectItem value="invoiced">Unpaid</SelectItem>
                <SelectItem value="paid">Paid</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <Spinner
            v-if="statusLoading || paymentStatusLoading"
            class="text-primary size-4 shrink-0"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Content -->
        <div class="flex flex-col gap-y-6 lg:col-span-2">
          <!-- Items Table -->
          <div class="border-border overflow-hidden rounded-lg border">
            <div class="border-border border-b px-4 py-3">
              <h4 class="font-medium tracking-tight">Order Items</h4>
            </div>

            <div v-if="order.items && order.items.length > 0" class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-border border-b">
                    <th
                      class="text-muted-foreground px-4 py-2.5 text-left text-xs font-medium tracking-tight"
                    >
                      Product
                    </th>
                    <th
                      class="text-muted-foreground px-4 py-2.5 text-left text-xs font-medium tracking-tight"
                    >
                      Category
                    </th>
                    <th
                      class="text-muted-foreground px-4 py-2.5 text-center text-xs font-medium tracking-tight"
                    >
                      Qty
                    </th>
                    <th
                      class="text-muted-foreground px-4 py-2.5 text-right text-xs font-medium tracking-tight"
                    >
                      Unit Price
                    </th>
                    <th
                      class="text-muted-foreground px-4 py-2.5 text-right text-xs font-medium tracking-tight"
                    >
                      Total
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="item in order.items"
                    :key="item.id"
                    class="border-border border-b last:border-0"
                  >
                    <td class="px-4 py-3">
                      <span class="font-medium tracking-tight">{{ item.product_name }}</span>
                      <p
                        v-if="item.notes"
                        class="text-muted-foreground mt-0.5 text-sm tracking-tight"
                      >
                        {{ item.notes }}
                      </p>

                      <!-- Per-item adjustments -->
                      <div v-if="itemAdjustments(item.id).length" class="mt-1.5 space-y-1">
                        <div
                          v-for="adj in itemAdjustments(item.id)"
                          :key="adj.id"
                          class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5"
                          :class="adj.is_voided ? 'opacity-50' : ''"
                        >
                          <span
                            :class="[
                              'inline-flex items-center rounded-full px-1.5 py-0 text-xs tracking-tight',
                              adj.kind === 'discount'
                                ? 'bg-success/15 text-success-foreground'
                                : 'bg-warning/15 text-warning-foreground',
                            ]"
                          >
                            {{ adj.kind_label }}
                          </span>
                          <span class="text-xs tracking-tight tabular-nums">
                            {{ adj.kind === "discount" ? "-" : "+" }}{{ formatPrice(adj.amount) }}
                          </span>
                          <button
                            v-if="!adj.is_voided && canVoid"
                            type="button"
                            class="text-destructive text-xs tracking-tight hover:underline"
                            @click="openVoidAdjustment(adj)"
                          >
                            Void
                          </button>
                        </div>
                      </div>

                      <button
                        v-if="canApplyManual"
                        type="button"
                        class="text-primary mt-1 inline-flex items-center gap-x-1 text-xs tracking-tight hover:underline"
                        @click="openItemAdjustment(item)"
                      >
                        <Icon name="lucide:plus" class="size-3 shrink-0" />
                        Adjust item
                      </button>
                    </td>
                    <td class="px-4 py-3">
                      <span class="text-muted-foreground tracking-tight">
                        {{ item.product_category || "-" }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                      <span class="tracking-tight">{{ item.quantity }}</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                      <span class="tracking-tight">{{ formatPrice(item.unit_price) }}</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                      <span class="font-medium tracking-tight">
                        {{ formatPrice(item.total_price ?? item.unit_price * item.quantity) }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-else class="flex items-center justify-center py-8">
              <p class="text-muted-foreground text-sm tracking-tight">No items found</p>
            </div>

            <!-- Totals -->
            <div class="border-border border-t px-4 py-3">
              <div class="ml-auto max-w-xs space-y-1.5">
                <div class="flex items-center justify-between gap-x-8 text-sm">
                  <span class="text-muted-foreground tracking-tight">Subtotal</span>
                  <span class="tracking-tight">{{ formatPrice(order.subtotal) }}</span>
                </div>
                <div
                  v-if="order.penalty_amount && parseFloat(order.penalty_amount) > 0"
                  class="flex items-center justify-between gap-x-8 text-sm"
                >
                  <span class="text-warning-foreground tracking-tight">Penalty</span>
                  <span class="text-warning-foreground tracking-tight">+{{ formatPrice(order.penalty_amount) }}</span>
                </div>
                <div
                  v-if="order.discount_amount && parseFloat(order.discount_amount) > 0"
                  class="flex items-center justify-between gap-x-8 text-sm"
                >
                  <span class="text-success-foreground tracking-tight">Discount</span>
                  <span class="text-success-foreground tracking-tight">-{{ formatPrice(order.discount_amount) }}</span>
                </div>
                <div
                  v-if="order.tax_amount != null"
                  class="flex items-center justify-between gap-x-8 text-sm"
                >
                  <span class="text-muted-foreground tracking-tight">Tax</span>
                  <span class="tracking-tight">{{ formatPrice(order.tax_amount) }}</span>
                </div>
                <div class="border-border border-t pt-1.5">
                  <div class="flex items-center justify-between gap-x-8 text-sm font-semibold">
                    <span class="tracking-tight">Total</span>
                    <span class="tracking-tight">{{ formatPrice(order.total) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Cancellation Reason -->
          <div
            v-if="order.operational_status === 'cancelled' && order.cancellation_reason"
            class="border-destructive/30 bg-destructive/5 rounded-lg border p-4"
          >
            <div class="flex items-center gap-x-2">
              <Icon name="hugeicons:alert-circle" class="text-destructive size-4 shrink-0" />
              <h4 class="text-destructive font-medium tracking-tight">Cancellation Reason</h4>
            </div>
            <p class="text-muted-foreground mt-1.5 text-sm tracking-tight whitespace-pre-line">
              {{ order.cancellation_reason }}
            </p>
          </div>

          <!-- Notes -->
          <div v-if="order.notes" class="border-border rounded-lg border p-4">
            <h4 class="mb-2 font-medium tracking-tight">Notes</h4>
            <p class="text-muted-foreground text-sm tracking-tight whitespace-pre-line">
              {{ order.notes }}
            </p>
          </div>

          <!-- Internal Notes (staff only) -->
          <div class="border-border rounded-lg border p-4">
            <div class="mb-3 flex items-center justify-between gap-2">
              <h4 class="font-medium tracking-tight">Internal Notes</h4>
              <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">Staff only</span>
            </div>
            <div class="space-y-3">
              <div class="space-y-1.5">
                <label class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  Order note
                </label>
                <textarea
                  v-model="internalNotesForm.order"
                  rows="2"
                  placeholder="Internal note for this order"
                  class="border-border bg-background placeholder:text-muted-foreground focus:ring-ring w-full rounded-md border px-3 py-2 text-sm tracking-tight outline-none focus:ring-1"
                />
              </div>
              <div
                v-for="item in order.items"
                :key="`note-${item.id}`"
                class="space-y-1.5"
              >
                <label class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  {{ item.product_name }}
                </label>
                <input
                  v-model="internalNotesForm.items[item.id]"
                  type="text"
                  placeholder="Internal note for this item"
                  class="border-border bg-background placeholder:text-muted-foreground focus:ring-ring w-full rounded-md border px-3 py-2 text-sm tracking-tight outline-none focus:ring-1"
                />
              </div>
              <div class="flex justify-end">
                <Button size="sm" :disabled="savingNotes" @click="saveInternalNotes">
                  <Spinner v-if="savingNotes" class="size-4" />
                  {{ savingNotes ? "Saving…" : "Save Notes" }}
                </Button>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="flex flex-col gap-y-4">
          <!-- Adjustments Controls -->
          <div class="border-border rounded-lg border p-4">
            <div class="flex items-center justify-between gap-2 mb-3">
              <h4 class="font-medium tracking-tight">Adjustments</h4>
              <Button
                v-if="canApplyManual"
                size="sm"
                variant="outline"
                @click="adjustmentDialogOpen = true"
              >
                <Icon name="lucide:plus" class="size-3.5 shrink-0" />
                Add
              </Button>
            </div>

            <div v-if="!orderLevelAdjustments.length" class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              No order-level adjustments applied.
            </div>

            <div v-else class="space-y-2">
              <div
                v-for="adj in orderLevelAdjustments"
                :key="adj.id"
                :class="[
                  'rounded-md border p-2',
                  adj.is_voided ? 'opacity-50' : '',
                ]"
              >
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                      <span
                        :class="[
                          'inline-flex items-center rounded-full px-1.5 py-0 text-xs tracking-tight',
                          adj.kind === 'discount' ? 'bg-success/15 text-success-foreground' : 'bg-warning/15 text-warning-foreground',
                        ]"
                      >
                        {{ adj.kind_label }}
                      </span>
                    </div>
                    <p class="text-xs tracking-tight mt-1 truncate">{{ adj.label }}</p>
                  </div>
                  <div class="text-right shrink-0">
                    <p class="text-xs tracking-tight tabular-nums font-medium">
                      {{ adj.kind === "discount" ? "-" : "+" }}{{ formatPrice(adj.amount) }}
                    </p>
                  </div>
                </div>
                <Button
                  v-if="!adj.is_voided && canVoid"
                  size="sm"
                  variant="ghost"
                  class="text-destructive h-6 px-1.5 text-xs mt-1 w-full justify-start"
                  @click="openVoidAdjustment(adj)"
                >
                  <Icon name="lucide:x" class="size-3 shrink-0" />
                  Void
                </Button>
              </div>
            </div>
          </div>

          <!-- Documents -->
          <div class="border-border rounded-lg border p-4">
            <h4 class="mb-3 font-medium tracking-tight">Documents</h4>
            <div class="space-y-4">
              <div v-for="doc in documentTypes" :key="doc.type" class="space-y-1.5">
                <div class="flex items-center justify-between gap-2">
                  <span class="text-sm font-medium tracking-tight">{{ doc.label }}</span>
                  <a
                    v-if="order[doc.type]?.url"
                    :href="order[doc.type].url"
                    target="_blank"
                    rel="noopener"
                    class="text-primary inline-flex items-center gap-x-1 text-xs tracking-tight hover:underline sm:text-sm"
                  >
                    <Icon name="hugeicons:download-04" class="size-3.5 shrink-0" />
                    Download
                  </a>
                </div>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">{{ doc.hint }}</p>
                <div class="flex flex-wrap gap-2">
                  <Button size="sm" variant="outline" @click="docState[doc.type].dialogOpen = true">
                    <Icon name="lucide:upload" class="size-3.5 shrink-0" />
                    {{ order[doc.type] ? "Replace" : "Upload" }}
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    :disabled="!order[doc.type] || docState[doc.type].sending || docCooldown(doc.type) > 0"
                    @click="handleSendDocument(doc.type)"
                  >
                    <Spinner v-if="docState[doc.type].sending" class="size-4" />
                    <Icon v-else name="lucide:send" class="size-3.5 shrink-0" />
                    {{ docSendLabel(doc.type) }}
                  </Button>
                </div>

                <DialogResponsive
                  v-model:open="docState[doc.type].dialogOpen"
                  :overflow-content="true"
                  dialog-max-width="28rem"
                >
                  <template #default>
                    <div class="px-4 pb-10 md:px-6 md:py-5">
                      <h3 class="text-lg font-semibold tracking-tight">Upload {{ doc.label }}</h3>
                      <p class="text-muted-foreground mt-1 text-sm tracking-tight">{{ doc.accept }}</p>
                      <form @submit.prevent="handleDocumentUpload(doc.type)" class="mt-4 space-y-4">
                        <InputFile
                          v-model="docState[doc.type].files"
                          :max-file-size="'20MB'"
                          :accepted-file-types="doc.mimes"
                        />
                        <div class="flex justify-end gap-2">
                          <Button
                            type="button"
                            variant="outline"
                            @click="docState[doc.type].dialogOpen = false"
                          >
                            Cancel
                          </Button>
                          <Button
                            type="submit"
                            :disabled="docState[doc.type].uploading || !docState[doc.type].files.length"
                          >
                            <Spinner v-if="docState[doc.type].uploading" class="size-4" />
                            {{ docState[doc.type].uploading ? "Uploading…" : "Upload" }}
                          </Button>
                        </div>
                      </form>
                    </div>
                  </template>
                </DialogResponsive>
              </div>
            </div>
          </div>

          <!-- Brand Info -->
          <div class="border-border rounded-lg border p-4">
            <h4 class="mb-3 font-medium tracking-tight">Brand</h4>
            <div class="space-y-2.5">
              <div>
                <p class="text-muted-foreground text-xs tracking-tight">Brand Name</p>
                <p class="text-sm font-medium tracking-tight">
                  {{ order.brand_event?.brand?.name ?? "-" }}
                </p>
              </div>
              <div v-if="order.brand_event?.brand?.company_name">
                <p class="text-muted-foreground text-xs tracking-tight">Company</p>
                <p class="text-sm tracking-tight">{{ order.brand_event.brand.company_name }}</p>
              </div>
              <div v-if="order.brand_event?.booth_number">
                <p class="text-muted-foreground text-xs tracking-tight">Booth Number</p>
                <p class="text-sm tracking-tight">{{ order.brand_event.booth_number }}</p>
              </div>
              <div v-if="order.brand_event?.booth_type_label">
                <p class="text-muted-foreground text-xs tracking-tight">Booth Type</p>
                <p class="text-sm tracking-tight">{{ order.brand_event.booth_type_label }}</p>
              </div>
            </div>
          </div>

          <!-- Dates -->
          <div class="border-border rounded-lg border p-4">
            <h4 class="mb-3 font-medium tracking-tight">Timeline</h4>
            <div class="space-y-2.5">
              <div>
                <p class="text-muted-foreground text-xs tracking-tight">Submitted</p>
                <p class="text-sm tracking-tight">
                  {{ formatDate(order.submitted_at || order.created_at) }}
                </p>
              </div>
              <div v-if="order.confirmed_at">
                <p class="text-muted-foreground text-xs tracking-tight">Confirmed</p>
                <p class="text-sm tracking-tight">{{ formatDate(order.confirmed_at) }}</p>
              </div>
              <div v-if="order.completed_at">
                <p class="text-muted-foreground text-xs tracking-tight">Completed</p>
                <p class="text-sm tracking-tight">{{ formatDate(order.completed_at) }}</p>
              </div>
            </div>
          </div>

          <!-- Creator Info -->
          <div v-if="order.creator" class="border-border rounded-lg border p-4">
            <h4 class="mb-3 font-medium tracking-tight">Submitted By</h4>
            <div class="flex items-center gap-x-2.5">
              <Avatar :model="order.creator" class="size-8 shrink-0" rounded="rounded-full" />
              <div class="min-w-0">
                <p class="truncate text-sm font-medium tracking-tight">
                  {{ order.creator.name }}
                </p>
                <p class="text-muted-foreground truncate text-xs tracking-tight">
                  {{ order.creator.email }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Cancellation Reason Dialog -->
    <DialogResponsive v-model:open="cancelDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Cancel Order</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            Provide a reason for cancelling this order.
          </p>
          <textarea
            v-model="cancellationReason"
            rows="3"
            placeholder="Cancellation reason"
            class="border-border bg-background placeholder:text-muted-foreground focus:ring-ring mt-3 w-full rounded-md border px-3 py-2 text-sm tracking-tight outline-none focus:ring-1"
          />
          <div class="mt-3 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              @click="cancelDialogOpen = false"
              :disabled="statusLoading"
            >
              Cancel
            </button>
            <button
              @click="confirmCancellation"
              :disabled="statusLoading || !cancellationReason.trim()"
              class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Spinner v-if="statusLoading" class="size-4 text-white" />
              <span v-else>Confirm Cancellation</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Manual Adjustment Dialog (order-level) -->
    <ManualAdjustmentDialog
      v-model:open="adjustmentDialogOpen"
      target-type="Order"
      :target-email="order?.brand_event?.brand?.company_email"
      @apply="handleApplyAdjustment"
    />

    <!-- Manual Adjustment Dialog (per-item) -->
    <ManualAdjustmentDialog
      v-model:open="itemAdjustmentDialogOpen"
      target-type="Order"
      manual-only
      :item-id="adjustItem?.id ?? null"
      :item-label="adjustItem?.product_name ?? ''"
      @apply="handleApplyAdjustment"
    />

    <!-- Void Adjustment Confirm -->
    <DialogResponsive v-model:open="voidDialogOpen" dialog-max-width="26rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Void Adjustment</h3>
          <p class="text-muted-foreground text-sm tracking-tight mt-2">
            Void "{{ voidTarget?.label }}"? Recalculates totals.
          </p>
          <div class="flex justify-end gap-2 pt-4">
            <Button variant="outline" @click="voidDialogOpen = false">Cancel</Button>
            <Button variant="destructive" @click="confirmVoid" :disabled="voiding">
              <Spinner v-if="voiding" class="size-4" />
              {{ voiding ? "Voiding..." : "Void" }}
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { toast } from "vue-sonner";

const props = defineProps({ event: Object, project: Object });

const route = useRoute();
const client = useSanctumClient();
const { hasPermission } = usePermission();

const canApplyManual = computed(() => hasPermission("promotions.apply_manual"));
const canVoid = computed(() => hasPermission("promotions.void_adjustment"));

const order = ref(null);
const loading = ref(true);
const statusLoading = ref(false);
const paymentStatusLoading = ref(false);

// Adjustment dialog state
const adjustmentDialogOpen = ref(false);
const voidDialogOpen = ref(false);
const voidTarget = ref(null);
const voiding = ref(false);

// Per-item adjustment state
const itemAdjustmentDialogOpen = ref(false);
const adjustItem = ref(null);

const orderLevelAdjustments = computed(
  () => order.value?.adjustments?.filter((a) => !a.order_item_id) ?? []
);

const itemAdjustments = (itemId) =>
  order.value?.adjustments?.filter((a) => a.order_item_id === itemId) ?? [];

const openItemAdjustment = (item) => {
  adjustItem.value = item;
  itemAdjustmentDialogOpen.value = true;
};

// Internal notes (staff only)
const savingNotes = ref(false);
const internalNotesForm = reactive({ order: "", items: {} });
let notesInitialized = false;

const syncInternalNotesForm = () => {
  if (!order.value) return;
  internalNotesForm.order = order.value.internal_notes ?? "";
  internalNotesForm.items = {};
  for (const item of order.value.items ?? []) {
    internalNotesForm.items[item.id] = item.internal_notes ?? "";
  }
  notesInitialized = true;
};

watch(
  order,
  () => {
    if (!notesInitialized) syncInternalNotesForm();
  },
  { immediate: true }
);

const saveInternalNotes = async () => {
  savingNotes.value = true;
  try {
    const res = await client(`${orderBase.value}/internal-notes`, {
      method: "PATCH",
      body: {
        internal_notes: internalNotesForm.order || null,
        items: Object.entries(internalNotesForm.items).map(([id, internal_notes]) => ({
          id: Number(id),
          internal_notes: internal_notes || null,
        })),
      },
    });
    if (res?.data) order.value = res.data;
    syncInternalNotesForm();
    toast.success("Internal notes saved");
  } catch (err) {
    toast.error("Failed to save notes", { description: err?.data?.message });
  } finally {
    savingNotes.value = false;
  }
};

// Documents (invoice + receipt) upload & send
const documentTypes = [
  {
    type: "invoice",
    label: "Invoice",
    hint: "Single PDF with invoice + faktur pajak.",
    accept: "PDF only. Max 20MB.",
    mimes: ["application/pdf"],
  },
  {
    type: "receipt",
    label: "Receipt",
    hint: "Proof of payment.",
    accept: "PDF, JPG, or PNG. Max 20MB.",
    mimes: ["application/pdf", "image/jpeg", "image/png"],
  },
];

const docState = reactive({
  invoice: { files: [], dialogOpen: false, uploading: false, sending: false, cooldownUntil: 0 },
  receipt: { files: [], dialogOpen: false, uploading: false, sending: false, cooldownUntil: 0 },
});

const cooldownTick = ref(Date.now());
let cooldownTimer = null;

const docCooldown = (type) =>
  Math.max(0, Math.ceil((docState[type].cooldownUntil - cooldownTick.value) / 1000));

const docSendLabel = (type) => {
  const remaining = docCooldown(type);
  if (remaining > 0) return `Resend in ${remaining}s`;
  return order.value?.[type] ? "Send" : "Send";
};

const startDocCooldown = (type, seconds = 60) => {
  docState[type].cooldownUntil = Date.now() + seconds * 1000;
  cooldownTick.value = Date.now();
  if (cooldownTimer) return;
  cooldownTimer = setInterval(() => {
    cooldownTick.value = Date.now();
    if (docCooldown("invoice") <= 0 && docCooldown("receipt") <= 0) {
      clearInterval(cooldownTimer);
      cooldownTimer = null;
    }
  }, 1000);
};

onBeforeUnmount(() => {
  if (cooldownTimer) clearInterval(cooldownTimer);
});

const handleDocumentUpload = async (type) => {
  const folder = docState[type].files?.[0];
  if (!folder || !String(folder).startsWith("tmp-")) return;
  docState[type].uploading = true;
  try {
    await client(`${orderBase.value}/${type}`, {
      method: "POST",
      body: { [`tmp_${type}`]: folder },
    });
    toast.success(`${type === "invoice" ? "Invoice" : "Receipt"} uploaded`);
    docState[type].dialogOpen = false;
    docState[type].files = [];
    await fetchOrder();
  } catch (err) {
    toast.error("Upload failed", { description: err?.data?.message || err?.message });
  } finally {
    docState[type].uploading = false;
  }
};

const handleSendDocument = async (type) => {
  docState[type].sending = true;
  try {
    await client(`${orderBase.value}/send-${type}`, { method: "POST" });
    toast.success(`${type === "invoice" ? "Invoice" : "Receipt"} email sent`);
    startDocCooldown(type);
  } catch (err) {
    const status = err?.response?.status || err?.status;
    const payload = err?.response?._data || err?.data;
    if (status === 429) {
      startDocCooldown(type, payload?.retry_after || 60);
      toast.error(payload?.message || "Please wait before resending.");
    } else {
      toast.error("Send failed", { description: payload?.message || err?.message });
    }
  } finally {
    docState[type].sending = false;
  }
};

usePageMeta(null, {
  title: computed(
    () => `Order ${order.value?.order_number || ""} · ${props.event?.title || "Event"}`
  ),
});

const orderBase = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/orders/${route.params.ulid}`
);

const handleApplyAdjustment = async (payload, setErrors) => {
  try {
    const res = await client(`${orderBase.value}/adjustments`, {
      method: "POST",
      body: payload,
    });
    toast.success(res?.message || "Adjustment applied");
    adjustmentDialogOpen.value = false;
    itemAdjustmentDialogOpen.value = false;
    if (res?.data?.order) {
      order.value = res.data.order;
    } else {
      await fetchOrder();
    }
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

const openVoidAdjustment = (adj) => {
  voidTarget.value = adj;
  voidDialogOpen.value = true;
};

const confirmVoid = async () => {
  if (!voidTarget.value) return;
  voiding.value = true;
  try {
    const res = await client(
      `${orderBase.value}/adjustments/${voidTarget.value.ulid}`,
      { method: "DELETE" },
    );
    toast.success("Adjustment voided");
    voidDialogOpen.value = false;
    voidTarget.value = null;
    if (res?.data) {
      order.value = res.data;
    } else {
      await fetchOrder();
    }
  } catch (err) {
    toast.error("Failed to void", { description: err?.data?.message });
  } finally {
    voiding.value = false;
  }
};

async function fetchOrder() {
  loading.value = true;
  try {
    const res = await client(
      `/api/projects/${route.params.username}/events/${route.params.eventSlug}/orders/${route.params.ulid}`
    );
    order.value = res.data;
  } catch (err) {
    toast.error("Failed to load order", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    loading.value = false;
  }
}

// Cancellation dialog
const cancelDialogOpen = ref(false);
const cancellationReason = ref("");

async function handleOperationalStatusUpdate(newStatus) {
  if (newStatus === "cancelled") {
    cancellationReason.value = "";
    cancelDialogOpen.value = true;
    return;
  }
  return updateOperationalStatus(newStatus);
}

async function confirmCancellation() {
  await updateOperationalStatus("cancelled", cancellationReason.value || null);
  cancelDialogOpen.value = false;
}

async function updateOperationalStatus(newStatus, cancellationReason = null) {
  statusLoading.value = true;
  try {
    const body = { operational_status: newStatus };
    if (cancellationReason) body.cancellation_reason = cancellationReason;

    const res = await client(
      `/api/projects/${route.params.username}/events/${route.params.eventSlug}/orders/${route.params.ulid}/operational-status`,
      { method: "PATCH", body }
    );
    order.value = res.data;
    toast.success("Operational status updated");
  } catch (err) {
    toast.error(err?.data?.message || "Failed to update status");
  } finally {
    statusLoading.value = false;
  }
}

async function handlePaymentStatusUpdate(newStatus) {
  paymentStatusLoading.value = true;
  try {
    const res = await client(
      `/api/projects/${route.params.username}/events/${route.params.eventSlug}/orders/${route.params.ulid}/payment-status`,
      { method: "PATCH", body: { payment_status: newStatus } }
    );
    order.value = res.data;
    toast.success("Payment status updated");
  } catch (err) {
    toast.error(err?.data?.message || "Failed to update payment status");
  } finally {
    paymentStatusLoading.value = false;
  }
}

function formatPrice(amount) {
  if (amount == null) return "-";
  return `Rp${Number(amount).toLocaleString("id-ID")}`;
}

function formatDate(dateStr) {
  if (!dateStr) return "-";
  return new Date(dateStr).toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

function statusBadgeClass(status) {
  const map = {
    submitted: "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400",
    confirmed: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
    processing: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
    completed: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400",
    cancelled: "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400",
  };
  return map[status] ?? "bg-muted text-muted-foreground";
}

onMounted(fetchOrder);
</script>
