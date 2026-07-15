<template>
  <div class="flex flex-col gap-y-6">
    <!-- Back Button -->
    <div>
      <ButtonBack :destination="ordersListUrl" force-destination />
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
      <!-- Header -->
      <div class="flex flex-col gap-y-4">
        <div class="flex flex-wrap items-center gap-3 lg:justify-between">
          <div class="flex flex-col gap-y-2 sm:flex-row sm:items-center sm:gap-x-4">
            <h3 class="font-mono text-lg font-semibold tracking-tight">
              {{ order.order_number }}
            </h3>
            <div class="flex flex-wrap items-center gap-1.5">
              <OrderStatusDropdown
                :status="order.operational_status"
                :statuses="operationalStatuses"
                :disabled="statusLoading"
                bordered
                @update="handleOperationalStatusUpdate"
              />
              <OrderStatusDropdown
                :status="order.payment_status"
                :statuses="paymentStatuses"
                :disabled="paymentStatusLoading"
                bordered
                @update="handlePaymentStatusUpdate"
              />
            </div>
          </div>

          <!-- Dialog triggers -->
          <div class="flex flex-wrap items-center gap-2">
            <Button
              v-if="canApplyManual || activeAdjustmentsCount"
              variant="outline"
              size="sm"
              @click="adjustmentsDialogOpen = true"
            >
              <Icon name="hugeicons:discount-tag-02" class="size-4 shrink-0" />
              Adjustments<span
                v-if="activeAdjustmentsCount"
                class="text-muted-foreground tabular-nums"
              >
                ({{ activeAdjustmentsCount }})</span
              >
            </Button>
            <Button variant="outline" size="sm" @click="documentsDialogOpen = true">
              <Icon name="hugeicons:invoice-03" class="size-4 shrink-0" />
              Documents
            </Button>
            <Button variant="outline" size="sm" @click="detailsDialogOpen = true">
              <Icon name="hugeicons:information-circle" class="size-4 shrink-0" />
              Details
            </Button>
          </div>
        </div>

        <!-- Brand detail -->
        <div v-if="order.brand_event?.brand" class="flex items-center gap-x-2.5">
          <Avatar
            :model="{
              name: order.brand_event.brand.name,
              profile_image: order.brand_event.brand.profile_image,
            }"
            class="size-9"
            rounded="rounded-md"
          />
          <div class="min-w-0 leading-tight">
            <p class="truncate text-sm font-medium tracking-tight">
              {{ order.brand_event.brand.name }}
            </p>
            <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
              <span v-if="order.brand_event.brand.company_name"
                >{{ order.brand_event.brand.company_name }} · </span
              >Submitted {{ formatDate(order.submitted_at || order.created_at) }}
            </p>
          </div>
        </div>
        <p v-else class="text-muted-foreground text-sm tracking-tight">
          Submitted {{ formatDate(order.submitted_at || order.created_at) }}
        </p>
      </div>

      <!-- Items table (full width) with totals footer -->
      <TableData
        :data="orderItems"
        :columns="itemColumns"
        :meta="itemsMeta"
        model="order-items"
        display-only
        :searchable="false"
        :column-toggle="false"
        :floating-actions="false"
        :show-pagination="false"
        :initial-pagination="{ pageIndex: 0, pageSize: 9999 }"
        :initial-sorting="[]"
      >
        <template #footer>
          <TableRow class="border-0 hover:bg-transparent">
            <TableCell
              :colspan="5"
              class="text-muted-foreground border-border border-t pt-3 pb-1.5 text-right text-sm tracking-tight"
            >
              Subtotal
            </TableCell>
            <TableCell
              class="border-border border-t pt-3 pb-1.5 text-right text-sm tracking-tight tabular-nums"
            >
              {{ formatPrice(order.subtotal, order.currency) }}
            </TableCell>
            <TableCell v-if="hasActionsCol" class="border-border border-t pt-3 pb-1.5" />
          </TableRow>

          <TableRow
            v-if="order.penalty_amount && parseFloat(order.penalty_amount) > 0"
            class="border-0 hover:bg-transparent"
          >
            <TableCell
              :colspan="5"
              class="text-warning-foreground border-0 py-1.5 text-right text-sm tracking-tight"
            >
              Penalty<span v-if="kindPct('penalty') != null">
                ({{ kindPct("penalty") }}%)</span
              >
            </TableCell>
            <TableCell
              class="text-warning-foreground border-0 py-1.5 text-right text-sm tracking-tight tabular-nums"
            >
              +{{ formatPrice(order.penalty_amount, order.currency) }}
            </TableCell>
            <TableCell v-if="hasActionsCol" class="border-0 py-1.5" />
          </TableRow>

          <TableRow
            v-if="order.discount_amount && parseFloat(order.discount_amount) > 0"
            class="border-0 hover:bg-transparent"
          >
            <TableCell
              :colspan="5"
              class="text-success-foreground border-0 py-1.5 text-right text-sm tracking-tight"
            >
              Discount<span v-if="kindPct('discount') != null">
                ({{ kindPct("discount") }}%)</span
              >
            </TableCell>
            <TableCell
              class="text-success-foreground border-0 py-1.5 text-right text-sm tracking-tight tabular-nums"
            >
              -{{ formatPrice(order.discount_amount, order.currency) }}
            </TableCell>
            <TableCell v-if="hasActionsCol" class="border-0 py-1.5" />
          </TableRow>

          <TableRow v-if="order.tax_amount != null" class="border-0 hover:bg-transparent">
            <TableCell
              :colspan="5"
              class="text-muted-foreground border-0 pt-1.5 pb-3 text-right text-sm tracking-tight"
            >
              Tax<span v-if="taxPct != null"> ({{ taxPct }}%)</span>
            </TableCell>
            <TableCell class="border-0 pt-1.5 pb-3 text-right text-sm tracking-tight tabular-nums">
              {{ formatPrice(order.tax_amount, order.currency) }}
            </TableCell>
            <TableCell v-if="hasActionsCol" class="border-0 pt-1.5 pb-3" />
          </TableRow>

          <TableRow class="border-0 hover:bg-transparent">
            <TableCell
              :colspan="5"
              class="border-border border-t py-3 text-right text-sm font-semibold tracking-tight"
            >
              Total
            </TableCell>
            <TableCell
              class="border-border border-t py-3 text-right text-sm font-semibold tracking-tight tabular-nums"
            >
              {{ formatPrice(order.total, order.currency) }}
            </TableCell>
            <TableCell v-if="hasActionsCol" class="border-border border-t py-3" />
          </TableRow>

          <!-- FX snapshot for USD orders: reporting is done in IDR (total_idr). -->
          <TableRow v-if="order.currency === 'USD'" class="border-0 hover:bg-transparent">
            <TableCell
              :colspan="5"
              class="text-muted-foreground border-border border-t py-1.5 text-right text-sm tracking-tight"
            >
              Exchange rate
            </TableCell>
            <TableCell
              class="text-muted-foreground border-border border-t py-1.5 text-right text-sm tracking-tight tabular-nums"
            >
              {{ formatPrice(order.exchange_rate_to_idr, "IDR") }} / USD
            </TableCell>
            <TableCell v-if="hasActionsCol" class="border-border border-t py-1.5" />
          </TableRow>

          <TableRow v-if="order.currency === 'USD'" class="border-0 hover:bg-transparent">
            <TableCell
              :colspan="5"
              class="text-muted-foreground border-0 pt-1.5 pb-3 text-right text-sm tracking-tight"
            >
              Total (IDR)
            </TableCell>
            <TableCell
              class="text-muted-foreground border-0 pt-1.5 pb-3 text-right text-sm tracking-tight tabular-nums"
            >
              {{ formatPrice(order.total_idr, "IDR") }}
            </TableCell>
            <TableCell v-if="hasActionsCol" class="border-0 pt-1.5 pb-3" />
          </TableRow>
        </template>
      </TableData>

      <!-- Internal Notes (order-level, staff only) — flat -->
      <div v-if="canManageOperational" class="max-w-2xl space-y-2.5">
        <div class="flex items-center gap-x-2">
          <h4 class="font-medium tracking-tight">Internal Notes</h4>
          <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">Staff only</span>
        </div>
        <Textarea
          v-model="internalNotesForm.order"
          rows="3"
          placeholder="Internal note for this order"
        />
        <div class="flex justify-end">
          <Button size="sm" :disabled="savingNotes" @click="saveInternalNotes">
            <Spinner v-if="savingNotes" class="size-4" />
            {{ savingNotes ? "Saving…" : "Save Note" }}
          </Button>
        </div>
      </div>

      <!-- Customer Notes — flat -->
      <div v-if="order.notes" class="space-y-1.5">
        <h4 class="font-medium tracking-tight">Customer Notes</h4>
        <p class="text-muted-foreground text-sm tracking-tight whitespace-pre-line">
          {{ order.notes }}
        </p>
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
    </template>

    <!-- ===== Dialogs ===== -->

    <!-- Adjustments Dialog -->
    <DialogResponsive v-model:open="adjustmentsDialogOpen" dialog-max-width="32rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Adjustments</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            Discounts & penalties applied to this order or its items.
          </p>
          <div v-if="canApplyManual" class="mt-3 flex justify-end">
            <Button size="sm" variant="outline" @click="openAddAdjustment">
              <Icon name="hugeicons:add-01" class="size-3.5 shrink-0" />
              Add adjustment
            </Button>
          </div>

          <div class="mt-4 space-y-2">
            <div
              v-if="!allAdjustments.length"
              class="text-muted-foreground py-6 text-center text-sm tracking-tight"
            >
              No adjustments applied.
            </div>
            <div
              v-for="adj in allAdjustments"
              :key="adj.id"
              class="border-border rounded-lg border p-3"
              :class="adj.is_voided ? 'opacity-50' : ''"
            >
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1 space-y-1">
                  <div class="flex flex-wrap items-center gap-1.5">
                    <Badge
                      :variant="adj.kind === 'discount' ? 'success' : 'warning'"
                      class="px-1.5 py-0.5 text-xs"
                    >
                      {{ adj.kind_label }}
                    </Badge>
                    <span class="text-muted-foreground text-xs tracking-tight">
                      {{ adj.order_item_id ? itemName(adj.order_item_id) : "Order-level" }}
                    </span>
                  </div>
                  <p class="text-muted-foreground truncate text-sm tracking-tight">{{ adj.label }}</p>
                </div>
                <p class="shrink-0 text-sm font-medium tracking-tight tabular-nums">
                  {{ adj.kind === "discount" ? "-" : "+"
                  }}{{ formatPrice(adj.amount, order.currency) }}
                </p>
              </div>
              <div v-if="!adj.is_voided && canVoid" class="mt-2 flex justify-end">
                <Button
                  size="sm"
                  variant="outline-destructive"
                  @click="openVoidFromDialog(adj)"
                >
                  <Icon name="hugeicons:cancel-01" class="size-4 shrink-0" />
                  Void
                </Button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Documents Dialog -->
    <DialogResponsive v-model:open="documentsDialogOpen" dialog-max-width="32rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Documents</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            Upload and send the invoice and receipt.
          </p>

          <div class="mt-4 space-y-5">
            <div v-for="doc in documentTypes" :key="doc.type" class="space-y-2">
              <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-medium tracking-tight">{{ doc.label }}</span>
                <AttachmentLink
                  v-if="order[doc.type]?.url"
                  :file="order[doc.type]"
                  :label="doc.label"
                  size="sm"
                />
              </div>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">{{ doc.accept }}</p>
              <InputFile
                v-model="docState[doc.type].files"
                :max-file-size="'20MB'"
                :accepted-file-types="doc.mimes"
              />
              <div class="flex flex-wrap justify-end gap-2">
                <Button
                  size="sm"
                  :disabled="docState[doc.type].uploading || !docState[doc.type].files.length"
                  @click="handleDocumentUpload(doc.type)"
                >
                  <Spinner v-if="docState[doc.type].uploading" class="size-4" />
                  <Icon v-else name="hugeicons:upload-01" class="size-3.5 shrink-0" />
                  {{ docState[doc.type].uploading ? "Uploading…" : order[doc.type] ? "Replace" : "Upload" }}
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  :disabled="!order[doc.type] || docState[doc.type].sending || docCooldown(doc.type) > 0"
                  @click="handleSendDocument(doc.type)"
                >
                  <Spinner v-if="docState[doc.type].sending" class="size-4" />
                  <Icon v-else name="hugeicons:sent" class="size-3.5 shrink-0" />
                  {{ docSendLabel(doc.type) }}
                </Button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Details Dialog -->
    <DialogResponsive v-model:open="detailsDialogOpen" dialog-max-width="28rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Order Details</h3>

          <div class="mt-4 space-y-4">
            <!-- Brand -->
            <div class="space-y-2.5">
              <div>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Brand Name</p>
                <p class="text-sm font-medium tracking-tight">
                  {{ order.brand_event?.brand?.name ?? "-" }}
                </p>
              </div>
              <div v-if="order.brand_event?.brand?.company_name">
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Company</p>
                <p class="text-sm tracking-tight">{{ order.brand_event.brand.company_name }}</p>
              </div>
              <div v-if="order.brand_event?.booth_number">
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Booth Number</p>
                <p class="text-sm tracking-tight">{{ order.brand_event.booth_number }}</p>
              </div>
              <div v-if="order.brand_event?.booth_type_label">
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Booth Type</p>
                <p class="text-sm tracking-tight">{{ order.brand_event.booth_type_label }}</p>
              </div>
            </div>

            <!-- Timeline -->
            <div class="border-border space-y-2.5 border-t pt-4">
              <div>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Submitted</p>
                <p class="text-sm tracking-tight">
                  {{ formatDate(order.submitted_at || order.created_at) }}
                </p>
              </div>
              <div v-if="order.confirmed_at">
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Confirmed</p>
                <p class="text-sm tracking-tight">{{ formatDate(order.confirmed_at) }}</p>
              </div>
              <div v-if="order.completed_at">
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Completed</p>
                <p class="text-sm tracking-tight">{{ formatDate(order.completed_at) }}</p>
              </div>
            </div>

            <!-- Submitted By -->
            <div v-if="order.creator" class="border-border border-t pt-4">
              <p class="text-muted-foreground mb-2 text-xs tracking-tight sm:text-sm">Submitted By</p>
              <div class="flex items-center gap-x-2.5">
                <Avatar :model="order.creator" class="size-8 shrink-0" rounded="rounded-full" />
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium tracking-tight">{{ order.creator.name }}</p>
                  <p class="text-muted-foreground truncate text-xs tracking-tight">
                    {{ order.creator.email }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Cancellation Reason Dialog -->
    <DialogResponsive v-model:open="cancelDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tight">Cancel Order</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            Provide a reason for cancelling this order.
          </p>
          <Textarea
            v-model="cancellationReason"
            rows="3"
            placeholder="Cancellation reason"
            class="mt-3"
          />
          <div class="mt-3 flex justify-end gap-2">
            <Button variant="outline" :disabled="statusLoading" @click="cancelDialogOpen = false">
              Cancel
            </Button>
            <Button
              variant="destructive"
              :disabled="statusLoading || !cancellationReason.trim()"
              @click="confirmCancellation"
            >
              <Spinner v-if="statusLoading" class="size-4" />
              {{ statusLoading ? "Cancelling…" : "Confirm Cancellation" }}
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Manual Adjustment Dialog (order-level) -->
    <ManualAdjustmentDialog
      v-model:open="adjustmentDialogOpen"
      target-type="Order"
      :target-email="order?.brand_event?.brand?.company_email"
      :currency="order?.currency"
      @apply="handleApplyAdjustment"
    />

    <!-- Manual Adjustment Dialog (per-item) -->
    <ManualAdjustmentDialog
      v-model:open="itemAdjustmentDialogOpen"
      target-type="Order"
      manual-only
      :item-id="adjustItem?.id ?? null"
      :item-label="adjustItem?.product_name ?? ''"
      :currency="order?.currency"
      @apply="handleApplyAdjustment"
    />

    <!-- Void Adjustment Confirm -->
    <DialogResponsive v-model:open="voidDialogOpen" dialog-max-width="26rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Void Adjustment</h3>
          <p class="text-muted-foreground mt-2 text-sm tracking-tight">
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
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { TableCell, TableRow } from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import OrderStatusDropdown from "@/components/order/StatusDropdown.vue";
import OrderItemActions from "@/components/order/ItemActions.vue";
import OrderItemNotesCell from "@/components/order/ItemNotesCell.vue";
import OrderItemProductCell from "@/components/order/ItemProductCell.vue";
import { toast } from "vue-sonner";

const props = defineProps({ event: Object, project: Object });

const route = useRoute();
const client = useSanctumClient();
const { hasPermission, hasRole, hasAnyPermission } = usePermission();
const { formatPrice } = useFormatters();

const canApplyManual = computed(() => hasPermission("promotions.apply_manual"));
const canVoid = computed(() => hasPermission("promotions.void_adjustment"));

// Mirror of OrderController::ensureCanManageOperational — gates internal notes.
const canManageOperational = computed(
  () =>
    hasRole("master") ||
    hasRole("admin") ||
    (hasRole("staff") && hasAnyPermission(["operational", "project-coordinator"]))
);

const operationalStatuses = [
  { value: "submitted", label: "Submitted", dot: "bg-blue-500" },
  { value: "confirmed", label: "Confirmed", dot: "bg-green-500" },
  { value: "processing", label: "Processing", dot: "bg-yellow-500" },
  { value: "completed", label: "Completed", dot: "bg-emerald-500" },
  { value: "cancelled", label: "Cancelled", dot: "bg-red-500" },
];

const paymentStatuses = [
  { value: "not_invoiced", label: "Not Invoiced", dot: "bg-gray-400" },
  { value: "invoiced", label: "Unpaid", dot: "bg-orange-500" },
  { value: "paid", label: "Paid", dot: "bg-green-500" },
];

const order = ref(null);
const loading = ref(true);
const statusLoading = ref(false);
const paymentStatusLoading = ref(false);

// Dialog open states
const adjustmentsDialogOpen = ref(false);
const documentsDialogOpen = ref(false);
const detailsDialogOpen = ref(false);

// Adjustment dialog state
const adjustmentDialogOpen = ref(false);
const voidDialogOpen = ref(false);
const voidTarget = ref(null);
const voiding = ref(false);

// Per-item adjustment state
const itemAdjustmentDialogOpen = ref(false);
const adjustItem = ref(null);

const allAdjustments = computed(() => order.value?.adjustments ?? []);
const activeAdjustmentsCount = computed(
  () => allAdjustments.value.filter((a) => !a.is_voided).length
);

const itemAdjustments = (itemId) =>
  order.value?.adjustments?.filter((a) => a.order_item_id === itemId) ?? [];

const itemName = (itemId) =>
  order.value?.items?.find((i) => i.id === itemId)?.product_name ?? `Item #${itemId}`;

const openItemAdjustment = (item) => {
  adjustItem.value = item;
  itemAdjustmentDialogOpen.value = true;
};

const openAddAdjustment = () => {
  adjustmentsDialogOpen.value = false;
  // Tunggu animasi close dialog list (~200ms) selesai sebelum membuka dialog
  // manual; membuka keduanya di tick yang sama membuat handoff reka-ui
  // membatalkan open dialog kedua.
  setTimeout(() => {
    adjustmentDialogOpen.value = true;
  }, 220);
};

const openVoidFromDialog = (adj) => {
  adjustmentsDialogOpen.value = false;
  setTimeout(() => {
    openVoidAdjustment(adj);
  }, 220);
};

const orderBase = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/orders/${route.params.ulid}`
);

const ordersListUrl = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}/operational/orders`
);

// Order items table (TableData). Sort by id for a stable display order — the
// API returns items in a non-deterministic order after mutations.
const orderItems = computed(() =>
  [...(order.value?.items ?? [])].sort((a, b) => a.id - b.id)
);

const itemsMeta = computed(() => ({
  current_page: 1,
  last_page: 1,
  per_page: order.value?.items?.length || 1,
  total: order.value?.items?.length || 0,
}));

const hasActionsCol = computed(() => canApplyManual.value);

const itemColumns = computed(() => {
  const cols = [
    {
      header: "Product",
      accessorKey: "product_name",
      cell: ({ row }) =>
        h(OrderItemProductCell, {
          item: row.original,
          adjustments: itemAdjustments(row.original.id),
          currency: order.value?.currency,
        }),
      size: 260,
      enableSorting: false,
      enableHiding: false,
    },
    {
      header: "Category",
      accessorKey: "product_category",
      cell: ({ row }) =>
        h(
          "span",
          { class: "text-muted-foreground text-sm tracking-tight" },
          row.original.product_category || "-"
        ),
      size: 120,
      enableSorting: false,
    },
    {
      header: "Internal Notes",
      accessorKey: "internal_notes",
      cell: ({ row }) =>
        h(OrderItemNotesCell, {
          item: row.original,
          orderBase: orderBase.value,
          canEdit: canManageOperational.value,
        }),
      size: 220,
      enableSorting: false,
      enableHiding: false,
    },
    {
      header: () => h("div", { class: "text-center" }, "Qty"),
      accessorKey: "quantity",
      cell: ({ row }) =>
        h(
          "div",
          { class: "text-center text-sm tracking-tight tabular-nums" },
          row.original.quantity
        ),
      size: 70,
      enableSorting: false,
    },
    {
      header: () => h("div", { class: "text-right" }, "Unit Price"),
      accessorKey: "unit_price",
      cell: ({ row }) =>
        h(
          "div",
          { class: "text-right text-sm tracking-tight tabular-nums" },
          formatPrice(row.original.unit_price, order.value?.currency)
        ),
      size: 140,
      enableSorting: false,
    },
    {
      header: () => h("div", { class: "text-right" }, "Total"),
      accessorKey: "total_price",
      cell: ({ row }) =>
        h(
          "div",
          { class: "text-right text-sm font-medium tracking-tight tabular-nums" },
          formatPrice(
            row.original.total_price ?? row.original.unit_price * row.original.quantity,
            order.value?.currency
          )
        ),
      size: 140,
      enableSorting: false,
    },
  ];

  if (canApplyManual.value) {
    cols.push({
      id: "actions",
      header: () => h("span", { class: "sr-only" }, "Actions"),
      cell: ({ row }) =>
        h(OrderItemActions, {
          item: row.original,
          onAdjust: openItemAdjustment,
        }),
      size: 56,
      enableSorting: false,
      enableHiding: false,
    });
  }

  return cols;
});

// Internal notes (staff only) — order-level. Per-item notes are saved inline.
const savingNotes = ref(false);
const internalNotesForm = reactive({ order: "" });
let notesInitialized = false;

const syncInternalNotesForm = () => {
  if (!order.value) return;
  internalNotesForm.order = order.value.internal_notes ?? "";
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
      body: { internal_notes: internalNotesForm.order || null },
    });
    if (res?.data) order.value = res.data;
    syncInternalNotesForm();
    toast.success("Internal note saved");
  } catch (err) {
    toast.error("Failed to save note", { description: err?.data?.message });
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
  invoice: { files: [], uploading: false, sending: false, cooldownUntil: 0 },
  receipt: { files: [], uploading: false, sending: false, cooldownUntil: 0 },
});

const cooldownTick = ref(Date.now());
let cooldownTimer = null;

const docCooldown = (type) =>
  Math.max(0, Math.ceil((docState[type].cooldownUntil - cooldownTick.value) / 1000));

const docSendLabel = (type) => {
  const remaining = docCooldown(type);
  if (remaining > 0) return `Resend in ${remaining}s`;
  return "Send";
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
    const res = await client(`${orderBase.value}/adjustments/${voidTarget.value.ulid}`, {
      method: "DELETE",
    });
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
    const res = await client(`${orderBase.value}`);
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

async function updateOperationalStatus(newStatus, reason = null) {
  statusLoading.value = true;
  try {
    const body = { operational_status: newStatus };
    if (reason) body.cancellation_reason = reason;

    const res = await client(`${orderBase.value}/operational-status`, { method: "PATCH", body });
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
    const res = await client(`${orderBase.value}/payment-status`, {
      method: "PATCH",
      body: { payment_status: newStatus },
    });
    order.value = res.data;
    toast.success("Payment status updated");
  } catch (err) {
    toast.error(err?.data?.message || "Failed to update payment status");
  } finally {
    paymentStatusLoading.value = false;
  }
}

// Configured tax rate (stored as a percent, e.g. 11.00).
const taxPct = computed(() => {
  const r = parseFloat(order.value?.tax_rate);
  return Number.isFinite(r) && r > 0 ? +r.toFixed(2) : null;
});

// Show a rate for a penalty/discount only when it maps to a single
// ORDER-LEVEL percentage-type adjustment. Per-item adjustments apply to one
// item's subtotal, not the whole order, so their rate is meaningless on the
// order footer. Fixed-amount (or mixed/multiple) adjustments also show no rate.
function kindPct(kind) {
  const adjs = (order.value?.adjustments ?? []).filter(
    (a) => !a.is_voided && !a.order_item_id && a.kind === kind
  );
  if (adjs.length !== 1 || adjs[0].value_type !== "percentage") {
    return null;
  }
  const v = parseFloat(adjs[0].value);
  return Number.isFinite(v) && v > 0 ? +v.toFixed(2) : null;
}

function formatDate(dateStr) {
  if (!dateStr) return "-";
  return new Date(dateStr).toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

onMounted(fetchOrder);
</script>
