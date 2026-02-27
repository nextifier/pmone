<template>
  <div class="flex flex-col gap-y-6">
    <!-- Back Button -->
    <div>
      <NuxtLink
        :to="`/projects/${route.params.username}/events/${route.params.eventSlug}/orders`"
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
            <h3 class="text-lg font-semibold tracking-tight font-mono">
              {{ order.order_number }}
            </h3>
            <span
              class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
              :class="statusBadgeClass(order.status)"
            >
              {{ order.status }}
            </span>
          </div>
          <p class="text-muted-foreground text-sm tracking-tight">
            Submitted {{ formatDate(order.submitted_at || order.created_at) }}
          </p>
        </div>

        <!-- Status Update -->
        <div class="flex items-center gap-x-2">
          <span class="text-muted-foreground text-sm tracking-tight">Update status:</span>
          <Select
            :model-value="order.status"
            :disabled="statusLoading"
            @update:model-value="handleStatusUpdate"
          >
            <SelectTrigger class="w-36">
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
          <Spinner v-if="statusLoading" class="size-4 shrink-0 text-primary" />
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
                  v-if="order.discount_amount && parseFloat(order.discount_amount) > 0"
                  class="flex items-center justify-between gap-x-8 text-sm"
                >
                  <span class="text-muted-foreground tracking-tight">
                    Discount
                    <span v-if="order.discount_type === 'percentage'">({{ order.discount_value }}%)</span>
                  </span>
                  <span class="tracking-tight text-green-600 dark:text-green-400">-{{ formatPrice(order.discount_amount) }}</span>
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

          <!-- Notes -->
          <div v-if="order.notes" class="border-border rounded-lg border p-4">
            <h4 class="mb-2 font-medium tracking-tight">Notes</h4>
            <p class="text-muted-foreground text-sm tracking-tight whitespace-pre-line">
              {{ order.notes }}
            </p>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="flex flex-col gap-y-4">
          <!-- Discount Controls -->
          <div class="border-border rounded-lg border p-4">
            <h4 class="mb-3 font-medium tracking-tight">Apply Discount</h4>
            <div class="space-y-2">
              <Select v-model="discountForm.type">
                <SelectTrigger class="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">No Discount</SelectItem>
                  <SelectItem value="percentage">Percentage</SelectItem>
                  <SelectItem value="fixed">Fixed Amount</SelectItem>
                </SelectContent>
              </Select>
              <div v-if="discountForm.type !== 'none'" class="flex items-center gap-x-2">
                <Input
                  v-model.number="discountForm.value"
                  type="number"
                  min="0"
                  :max="discountForm.type === 'percentage' ? 100 : undefined"
                  step="any"
                  :placeholder="discountForm.type === 'percentage' ? 'e.g. 10' : 'e.g. 500000'"
                  class="flex-1"
                />
                <span v-if="discountForm.type === 'percentage'" class="text-muted-foreground text-sm">%</span>
              </div>
              <Button
                size="sm"
                class="w-full"
                :disabled="applyingDiscount"
                @click="applyDiscount"
              >
                <Spinner v-if="applyingDiscount" class="mr-1 size-3.5" />
                {{ discountForm.type === 'none' ? 'Remove Discount' : 'Apply' }}
              </Button>
            </div>
          </div>

          <!-- Brand Info -->
          <div class="border-border rounded-lg border p-4">
            <h4 class="mb-3 font-medium tracking-tight">Brand</h4>
            <div class="space-y-2.5">
              <div>
                <p class="text-muted-foreground text-xs tracking-tight">Brand Name</p>
                <p class="text-sm font-medium tracking-tight">{{ order.brand_event?.brand?.name ?? "-" }}</p>
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
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
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

const order = ref(null);
const loading = ref(true);
const statusLoading = ref(false);

// Discount form
const discountForm = reactive({ type: "none", value: 0 });
const applyingDiscount = ref(false);

usePageMeta(null, {
  title: computed(() => `Order ${order.value?.order_number || ""} · ${props.event?.title || "Event"}`),
});

async function fetchOrder() {
  loading.value = true;
  try {
    const res = await client(
      `/api/projects/${route.params.username}/events/${route.params.eventSlug}/orders/${route.params.ulid}`
    );
    order.value = res.data;
    discountForm.type = res.data.discount_type || "none";
    discountForm.value = res.data.discount_value ? parseFloat(res.data.discount_value) : 0;
  } catch (err) {
    toast.error("Failed to load order", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    loading.value = false;
  }
}

async function handleStatusUpdate(newStatus) {
  statusLoading.value = true;
  try {
    const res = await client(
      `/api/projects/${route.params.username}/events/${route.params.eventSlug}/orders/${route.params.ulid}/status`,
      {
        method: "PATCH",
        body: { status: newStatus },
      }
    );
    order.value = res.data;
    toast.success("Order status updated");
  } catch (err) {
    toast.error(err?.data?.message || "Failed to update status");
  } finally {
    statusLoading.value = false;
  }
}

async function applyDiscount() {
  applyingDiscount.value = true;
  try {
    const body =
      discountForm.type === "none"
        ? { discount_type: null, discount_value: null }
        : { discount_type: discountForm.type, discount_value: discountForm.value };

    const res = await client(
      `/api/projects/${route.params.username}/events/${route.params.eventSlug}/orders/${route.params.ulid}/discount`,
      { method: "PATCH", body },
    );
    order.value = res.data;
    discountForm.type = res.data.discount_type || "none";
    discountForm.value = res.data.discount_value ? parseFloat(res.data.discount_value) : 0;
    toast.success(discountForm.type === "none" ? "Discount removed" : "Discount applied");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to apply discount");
  } finally {
    applyingDiscount.value = false;
  }
}

function formatPrice(amount) {
  if (amount == null) return "-";
  return `Rp ${Number(amount).toLocaleString("id-ID")}`;
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
