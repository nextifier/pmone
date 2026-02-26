<template>
  <div class="mx-auto max-w-3xl px-4 py-6">
    <!-- Back link -->
    <NuxtLink
      :to="`/projects/${route.params.username}/events/${route.params.slug}/orders`"
      class="text-muted-foreground hover:text-foreground mb-6 inline-flex items-center gap-x-1.5 text-sm transition"
    >
      <Icon name="hugeicons:arrow-left-01" class="size-4" />
      Back to Orders
    </NuxtLink>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <template v-else-if="order">
      <!-- Order header -->
      <div class="flex items-start justify-between gap-x-4">
        <div>
          <p class="font-mono text-xl font-semibold tracking-tight">
            {{ order.order_number }}
          </p>
          <div class="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-3 text-xs">
            <span v-if="order.brand_event?.brand?.name">
              {{ order.brand_event.brand.name }}
              <span v-if="order.brand_event.brand.company_name"> ({{ order.brand_event.brand.company_name }})</span>
            </span>
            <span v-if="order.submitted_at">
              Submitted {{ formatDate(order.submitted_at) }}
            </span>
          </div>
        </div>
        <Badge :class="statusClass(order.status)" class="shrink-0 capitalize">
          {{ order.status }}
        </Badge>
      </div>

      <!-- Status & Discount Controls -->
      <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <!-- Status Update -->
        <div class="border-border rounded-lg border p-4">
          <h3 class="mb-3 text-sm font-semibold tracking-tight">{{ $t('orderDetail.statusUpdate') }}</h3>
          <div class="flex items-center gap-x-2">
            <Select v-model="statusForm.status">
              <SelectTrigger class="flex-1">
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
            <Button size="sm" :disabled="updatingStatus || statusForm.status === order.status" @click="updateStatus">
              <Icon v-if="updatingStatus" name="svg-spinners:ring-resize" class="mr-1 size-3.5" />
              {{ $t('common.save') }}
            </Button>
          </div>
        </div>

        <!-- Discount Controls -->
        <div class="border-border rounded-lg border p-4">
          <h3 class="mb-3 text-sm font-semibold tracking-tight">{{ $t('orderDetail.applyDiscount') }}</h3>
          <div class="space-y-2">
            <Select v-model="discountForm.type">
              <SelectTrigger class="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">{{ $t('orderDetail.noDiscount') }}</SelectItem>
                <SelectItem value="percentage">{{ $t('orderDetail.percentage') }}</SelectItem>
                <SelectItem value="fixed">{{ $t('orderDetail.fixed') }}</SelectItem>
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
              <Icon v-if="applyingDiscount" name="svg-spinners:ring-resize" class="mr-1 size-3.5" />
              {{ discountForm.type === 'none' ? $t('orderDetail.removeDiscount') : $t('orderDetail.apply') }}
            </Button>
          </div>
        </div>
      </div>

      <!-- Order items -->
      <div class="frame mt-6">
        <div class="frame-header">
          <div class="frame-title">{{ $t('orderDetail.orderItems') }}</div>
        </div>
        <div class="frame-panel p-0">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b">
                <th class="text-muted-foreground px-4 py-3 text-left font-medium">{{ $t('orderDetail.product') }}</th>
                <th class="text-muted-foreground px-4 py-3 text-left font-medium">{{ $t('orderDetail.category') }}</th>
                <th class="text-muted-foreground px-4 py-3 text-right font-medium">{{ $t('orderDetail.qty') }}</th>
                <th class="text-muted-foreground px-4 py-3 text-right font-medium">{{ $t('orderDetail.unitPrice') }}</th>
                <th class="text-muted-foreground px-4 py-3 text-right font-medium">{{ $t('orderDetail.total') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(item, index) in order.items"
                :key="item.id ?? index"
                class="border-b last:border-0"
              >
                <td class="px-4 py-3">
                  <div class="flex items-center gap-x-3">
                    <img
                      v-if="item.product_image_url"
                      :src="item.product_image_url"
                      :alt="item.product_name"
                      class="size-10 shrink-0 rounded object-cover"
                    />
                    <span class="font-medium">{{ item.product_name }}</span>
                  </div>
                </td>
                <td class="text-muted-foreground px-4 py-3">{{ item.product_category ?? "-" }}</td>
                <td class="px-4 py-3 text-right">{{ item.quantity }}</td>
                <td class="px-4 py-3 text-right">{{ formatPrice(item.unit_price) }}</td>
                <td class="px-4 py-3 text-right font-medium">{{ formatPrice(item.total_price) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Totals -->
      <div class="mt-4 flex justify-end">
        <div class="w-64 space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-muted-foreground">{{ $t('orderDetail.subtotal') }}</span>
            <span>{{ formatPrice(order.subtotal) }}</span>
          </div>
          <div v-if="order.discount_amount && parseFloat(order.discount_amount) > 0" class="flex justify-between">
            <span class="text-muted-foreground">
              {{ $t('orderDetail.discount') }}
              <span v-if="order.discount_type === 'percentage'">({{ order.discount_value }}%)</span>
            </span>
            <span class="text-green-600 dark:text-green-400">-{{ formatPrice(order.discount_amount) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-muted-foreground">{{ $t('orderDetail.tax', { rate: order.tax_rate }) }}</span>
            <span>{{ formatPrice(order.tax_amount) }}</span>
          </div>
          <div class="flex justify-between border-t pt-2 font-semibold">
            <span>{{ $t('orderDetail.total') }}</span>
            <span>{{ formatPrice(order.total) }}</span>
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div v-if="order.notes" class="frame mt-6">
        <div class="frame-header">
          <div class="frame-title">{{ $t('orderDetail.notes') }}</div>
        </div>
        <div class="frame-panel">
          <p class="text-sm whitespace-pre-wrap">{{ order.notes }}</p>
        </div>
      </div>
    </template>

    <!-- Not found -->
    <div v-else class="flex flex-col items-center justify-center gap-3 py-20">
      <div class="bg-muted flex size-12 items-center justify-center rounded-full">
        <Icon name="hugeicons:shopping-bag-01" class="text-muted-foreground size-6" />
      </div>
      <p class="text-muted-foreground text-sm">{{ $t('orderDetail.notFound') }}</p>
    </div>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
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

const { t } = useI18n();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const client = useSanctumClient();
const order = ref(null);
const loading = ref(true);

const apiBase = computed(
  () => `/api/projects/${route.params.username}/events/${route.params.slug}/orders/${route.params.ulid}`,
);

// Status form
const statusForm = reactive({ status: "" });
const updatingStatus = ref(false);

// Discount form
const discountForm = reactive({ type: "none", value: 0 });
const applyingDiscount = ref(false);

async function fetchOrder() {
  loading.value = true;
  try {
    const res = await client(apiBase.value);
    order.value = res.data;
    statusForm.status = res.data.status;
    discountForm.type = res.data.discount_type || "none";
    discountForm.value = res.data.discount_value ? parseFloat(res.data.discount_value) : 0;
  } catch {
    toast.error(t("orderDetail.failedToLoad"));
  } finally {
    loading.value = false;
  }
}

async function updateStatus() {
  updatingStatus.value = true;
  try {
    const res = await client(`${apiBase.value}/status`, {
      method: "PATCH",
      body: { status: statusForm.status },
    });
    order.value = res.data;
    toast.success("Status updated");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to update status");
  } finally {
    updatingStatus.value = false;
  }
}

async function applyDiscount() {
  applyingDiscount.value = true;
  try {
    const body =
      discountForm.type === "none"
        ? { discount_type: null, discount_value: null }
        : { discount_type: discountForm.type, discount_value: discountForm.value };

    const res = await client(`${apiBase.value}/discount`, {
      method: "PATCH",
      body,
    });
    order.value = res.data;
    discountForm.type = res.data.discount_type || "none";
    discountForm.value = res.data.discount_value ? parseFloat(res.data.discount_value) : 0;
    toast.success(
      discountForm.type === "none" ? t("orderDetail.discountRemoved") : t("orderDetail.discountApplied"),
    );
  } catch (e) {
    toast.error(e?.data?.message || t("orderDetail.failedToApplyDiscount"));
  } finally {
    applyingDiscount.value = false;
  }
}

const { formatPrice, formatDateId: formatDate, orderStatusClass: statusClass } = useFormatters();

onMounted(fetchOrder);
usePageMeta(null, {
  title: computed(() => (order.value ? `Order ${order.value.order_number}` : "Order Detail")),
});
</script>
