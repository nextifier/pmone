<template>
  <div class="container py-6 pb-16">
    <!-- Loading skeleton -->
    <template v-if="loading">
      <div class="space-y-4">
        <div class="bg-muted h-8 w-64 animate-pulse rounded" />
        <div class="bg-muted h-4 w-40 animate-pulse rounded" />
      </div>
      <div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
          <div v-for="i in 3" :key="i" class="bg-muted h-32 animate-pulse rounded-lg" />
        </div>
        <div class="bg-muted h-64 animate-pulse rounded-lg lg:col-span-1" />
      </div>
    </template>

    <template v-else-if="info">
      <!-- Header -->
      <div class="space-y-1">
        <div class="mb-6 flex flex-col items-start gap-y-3">
          <BackButton :show-label="true" />
        </div>

        <div class="text-muted-foreground flex flex-wrap items-center gap-x-2 text-sm">
          <span>{{ info.brand?.name }}</span>
          <span class="text-muted-foreground/40">·</span>
          <span>{{ info.event?.title }}</span>
        </div>
        <h1 class="text-2xl font-medium tracking-tight">{{ $t("orderForm.title") }}</h1>
        <div
          v-if="info.brand_event?.booth_number || info.brand_event?.booth_type"
          class="text-muted-foreground flex flex-wrap items-center gap-x-3 text-sm"
        >
          <span v-if="info.brand_event?.booth_number">
            {{ $t("orderForm.booth") }}
            <span class="text-foreground font-medium">{{ info.brand_event.booth_number }}</span>
          </span>
          <span v-if="info.brand_event?.booth_type_label">
            {{ info.brand_event.booth_type_label }}
          </span>
        </div>
      </div>

      <!-- T&C Section -->
      <div
        v-if="info.order_form_content"
        class="border-border bg-muted/30 mt-6 rounded-lg border p-5"
      >
        <h2 class="mb-3 text-sm font-medium tracking-tight">
          {{ $t("orderForm.termsAndConditions") }}
        </h2>
        <div
          class="prose prose-sm dark:prose-invert max-w-none text-sm"
          v-html="info.order_form_content"
        />
      </div>

      <!-- Order Period Banner -->
      <div
        v-if="info.current_period === 'onsite_order'"
        class="mt-4 flex items-center gap-x-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300"
      >
        <Icon name="hugeicons:alert-02" class="size-4 shrink-0" />
        <span>
          Onsite order period - all prices include a {{ info.penalty_rate }}% surcharge.
        </span>
      </div>

      <!-- Order Closed Banner -->
      <div
        v-if="!canOrder"
        class="mt-4 flex items-center gap-x-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300"
      >
        <Icon name="hugeicons:alert-02" class="size-4 shrink-0" />
        <span>The order form is currently closed. No active order period at this time.</span>
      </div>

      <!-- Main content grid -->
      <div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-3">
        <!-- Product Catalog (2/3) -->
        <div class="space-y-6 lg:col-span-2">
          <template v-if="products.length > 0">
            <div v-for="category in products" :key="category.category" class="space-y-2">
              <!-- Category header (collapsible) -->
              <button
                @click="toggleCategory(category.category)"
                class="flex w-full items-center justify-between py-1"
              >
                <h3 class="text-sm font-medium tracking-tight uppercase">
                  {{ category.category }}
                  <span class="text-muted-foreground ml-1 font-normal normal-case">
                    ({{ category.products.length }})
                  </span>
                </h3>
                <Icon
                  :name="
                    collapsedCategories.includes(category.category)
                      ? 'lucide:chevron-right'
                      : 'lucide:chevron-down'
                  "
                  class="text-muted-foreground size-4 shrink-0"
                />
              </button>

              <div
                v-if="category.description && !collapsedCategories.includes(category.category)"
                class="prose prose-sm dark:prose-invert text-muted-foreground max-w-none"
                v-html="category.description"
              />

              <div v-if="!collapsedCategories.includes(category.category)" class="space-y-2">
                <!-- Catalog PDF -->
                <a
                  v-if="category.catalog_file"
                  :href="category.catalog_file.url"
                  target="_blank"
                  class="border-border bg-muted/50 hover:bg-muted flex items-center gap-x-2 rounded-lg border p-4 transition"
                >
                  <Icon name="teenyicons:pdf-solid" class="text-destructive size-10 shrink-0" />
                  <span class="truncate text-sm tracking-tight">{{
                    category.catalog_file.name
                  }}</span>
                  <Icon
                    name="hugeicons:arrow-up-right-01"
                    class="text-muted-foreground ml-auto size-3.5 shrink-0"
                  />
                </a>

                <div
                  v-for="product in category.products"
                  :key="product.id"
                  class="border-border flex items-center justify-between rounded-lg border p-4"
                >
                  <div class="flex min-w-0 flex-1 items-start gap-x-3 pr-4">
                    <img
                      v-if="product.product_image?.sm"
                      :src="product.product_image.sm"
                      :alt="product.name"
                      class="size-12 shrink-0 rounded-lg object-cover"
                    />
                    <div class="min-w-0 flex-1">
                      <p class="font-medium tracking-tight">{{ product.name }}</p>
                      <p
                        v-if="product.description"
                        class="text-muted-foreground mt-0.5 text-xs tracking-tight sm:text-sm"
                      >
                        {{ product.description }}
                      </p>
                      <p class="mt-1 text-sm font-medium">
                        <template v-if="isOnsite">
                          <span class="text-muted-foreground text-xs line-through sm:text-sm">{{ formatPrice(product.price) }}</span>
                          {{ formatPrice(withPenalty(product.price)) }}
                        </template>
                        <template v-else>
                          {{ formatPrice(product.price) }}
                        </template>
                        <span class="text-muted-foreground font-normal">/ {{ product.unit }}</span>
                      </p>
                    </div>
                  </div>

                  <!-- Add to cart or quantity controls -->
                  <div class="shrink-0">
                    <template v-if="getCartItem(product.id)">
                      <div class="flex items-center gap-x-1">
                        <button
                          @click="updateQuantity(product.id, getCartItem(product.id).quantity - 1)"
                          class="border-border hover:bg-muted flex size-7 items-center justify-center rounded border text-sm"
                        >
                          -
                        </button>
                        <span class="w-8 text-center text-sm">
                          {{ getCartItem(product.id).quantity }}
                        </span>
                        <button
                          @click="updateQuantity(product.id, getCartItem(product.id).quantity + 1)"
                          class="border-border hover:bg-muted flex size-7 items-center justify-center rounded border text-sm"
                        >
                          +
                        </button>
                      </div>
                    </template>
                    <template v-else>
                      <Button
                        size="sm"
                        variant="outline"
                        :disabled="isDeadlinePassed"
                        @click="handleAddToCart(product, category.category)"
                      >
                        <Icon name="lucide:plus" class="mr-1 size-3.5" />
                        {{ $t("common.add") }}
                      </Button>
                    </template>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <div v-else class="text-muted-foreground py-12 text-center text-sm">
            {{ $t("orderForm.noProducts") }}
          </div>
        </div>

        <!-- Cart Panel (1/3) -->
        <div ref="cartPanelRef" class="lg:col-span-1">
          <div class="sticky top-20 space-y-4">
            <div class="border-border rounded-lg border p-4">
              <div class="mb-3 flex items-center justify-between">
                <h3 class="flex items-center font-medium tracking-tight">
                  {{ $t("orderForm.orderCart") }}
                  <span
                    v-if="itemCount > 0"
                    class="bg-primary text-primary-foreground ml-1.5 inline-flex size-5 items-center justify-center rounded-full text-[11px] font-medium"
                  >
                    {{ itemCount }}
                  </span>
                </h3>
                <button
                  v-if="cartItems.length > 0"
                  @click="clearCart"
                  class="text-muted-foreground hover:text-destructive text-xs sm:text-sm"
                >
                  {{ $t("orderForm.clearAll") }}
                </button>
              </div>

              <!-- Empty cart state -->
              <div
                v-if="cartItems.length === 0"
                class="text-muted-foreground py-8 text-center text-sm"
              >
                <Icon name="lucide:shopping-cart" class="mx-auto mb-2 size-8 opacity-30" />
                <p>{{ $t("orderForm.cartEmpty") }}</p>
                <p class="mt-0.5 text-xs sm:text-sm">{{ $t("orderForm.addFromCatalog") }}</p>
              </div>

              <!-- Cart items -->
              <template v-else>
                <div class="divide-border divide-y">
                  <div
                    v-for="item in cartItems"
                    :key="item.event_product_id"
                    class="space-y-2 py-3 first:pt-0"
                  >
                    <div class="flex items-start justify-between gap-x-3">
                      <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ item.name }}</p>
                        <p class="text-muted-foreground mt-0.5 text-sm">
                          <template v-if="isOnsite">
                            <span class="text-xs line-through sm:text-sm">{{ formatPrice(item.price) }}</span>
                            {{ formatPrice(withPenalty(item.price)) }}
                          </template>
                          <template v-else>
                            {{ formatPrice(item.price) }}
                          </template>
                          x {{ item.quantity }}
                        </p>
                      </div>
                      <div class="flex items-center gap-x-1">
                        <button
                          @click="updateQuantity(item.event_product_id, item.quantity - 1)"
                          class="border-border hover:bg-muted flex size-7 items-center justify-center rounded border text-sm"
                        >
                          -
                        </button>
                        <span class="w-8 text-center text-sm">{{ item.quantity }}</span>
                        <button
                          @click="updateQuantity(item.event_product_id, item.quantity + 1)"
                          class="border-border hover:bg-muted flex size-7 items-center justify-center rounded border text-sm"
                        >
                          +
                        </button>
                        <button
                          @click="removeItem(item.event_product_id)"
                          class="text-muted-foreground hover:text-destructive ml-1"
                        >
                          <Icon name="hugeicons:delete-02" class="size-4" />
                        </button>
                      </div>
                    </div>

                    <!-- Item notes -->
                    <Textarea
                      :model-value="item.notes"
                      @update:model-value="updateItemNotes(item.event_product_id, $event)"
                      :placeholder="$t('orderForm.notesOptional')"
                      rows="2"
                      class="text-sm"
                    />
                  </div>
                </div>

                <!-- Totals -->
                <div class="border-border mt-3 space-y-1.5 border-t pt-3 text-sm">
                  <div class="flex justify-between">
                    <span class="text-muted-foreground">{{ $t("orderForm.subtotal") }}</span>
                    <span>
                      <template v-if="isOnsite">
                        <span class="text-muted-foreground text-xs line-through sm:text-sm">{{ formatPrice(subtotal) }}</span>
                        {{ formatPrice(subtotalWithPenalty) }}
                      </template>
                      <template v-else>
                        {{ formatPrice(subtotal) }}
                      </template>
                    </span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-muted-foreground">{{
                      $t("orderForm.tax", { rate: taxRate })
                    }}</span>
                    <span>{{ formatPrice(computedTaxAmount) }}</span>
                  </div>
                  <div class="border-border flex justify-between border-t pt-1.5 font-semibold">
                    <span>{{ $t("orderForm.total") }}</span>
                    <span>{{ formatPrice(computedTotal) }}</span>
                  </div>
                </div>

                <!-- General notes -->
                <div class="mt-4 space-y-1.5">
                  <label class="text-xs sm:text-sm font-medium">{{ $t("orderForm.orderNotes") }}</label>
                  <Textarea
                    v-model="orderNotes"
                    :placeholder="$t('orderForm.specialInstructions')"
                    rows="3"
                    class="text-sm"
                  />
                </div>

                <!-- Submit button -->
                <Button
                  class="mt-4 w-full"
                  @click="showConfirmDialog = true"
                  :disabled="cartItems.length === 0 || isDeadlinePassed"
                >
                  {{ $t("orderForm.reviewAndSubmit") }}
                </Button>
              </template>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Mobile Floating Cart Button -->
    <Teleport to="body">
      <button
        v-if="itemCount > 0 && !loading"
        class="bg-primary text-primary-foreground fixed right-4 bottom-4 z-40 flex items-center gap-x-2 rounded-full px-4 py-3 text-sm font-medium shadow-lg transition active:scale-95 lg:hidden"
        @click="scrollToCart"
      >
        <Icon name="lucide:shopping-cart" class="size-4" />
        {{ $t("orderForm.viewCart") }}
        <span
          class="bg-primary-foreground text-primary inline-flex size-5 items-center justify-center rounded-full text-[11px] font-semibold"
        >
          {{ itemCount }}
        </span>
      </button>
    </Teleport>

    <!-- Confirmation Dialog -->
    <DialogResponsive
      v-model:open="showConfirmDialog"
      dialog-max-width="450px"
      :overflow-content="true"
    >
      <template #sticky-header>
        <div
          class="border-border sticky top-0 z-10 -mt-4 border-b px-4 pb-2 text-center md:mt-0 md:px-6 md:py-3.5 md:text-left"
        >
          <div class="text-lg font-semibold tracking-tighter">
            {{ $t("orderForm.confirmOrder") }}
          </div>
          <p class="text-muted-foreground mt-0.5 text-sm tracking-tight">
            {{ $t("orderForm.reviewBeforeSubmitting") }}
          </p>
        </div>
      </template>
      <template #default>
        <div class="px-4 py-4 md:px-6">
          <div class="space-y-3">
            <!-- Order summary -->
            <div class="divide-border divide-y">
              <div
                v-for="item in cartItems"
                :key="item.event_product_id"
                class="flex items-start justify-between gap-x-3 py-2.5 first:pt-0"
              >
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-medium">{{ item.name }}</p>
                  <p class="text-muted-foreground text-xs sm:text-sm">
                    {{ item.quantity }} ×
                    <template v-if="isOnsite">
                      <span class="text-xs line-through">{{ formatPrice(item.price) }}</span>
                      {{ formatPrice(withPenalty(item.price)) }}
                    </template>
                    <template v-else>
                      {{ formatPrice(item.price) }}
                    </template>
                  </p>
                  <p v-if="item.notes" class="text-muted-foreground mt-0.5 text-xs sm:text-sm italic">
                    "{{ item.notes }}"
                  </p>
                </div>
                <span class="shrink-0 text-sm font-medium">
                  <template v-if="isOnsite">
                    {{ formatPrice(withPenalty(item.price) * item.quantity) }}
                  </template>
                  <template v-else>
                    {{ formatPrice(item.price * item.quantity) }}
                  </template>
                </span>
              </div>
            </div>

            <!-- Totals summary -->
            <div class="border-border space-y-1.5 border-t pt-3 text-sm">
              <div class="flex justify-between">
                <span class="text-muted-foreground">{{ $t("orderForm.subtotal") }}</span>
                <span>
                  <template v-if="isOnsite">
                    <span class="text-muted-foreground text-xs line-through sm:text-sm">{{ formatPrice(subtotal) }}</span>
                    {{ formatPrice(subtotalWithPenalty) }}
                  </template>
                  <template v-else>
                    {{ formatPrice(subtotal) }}
                  </template>
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-muted-foreground">{{
                  $t("orderForm.tax", { rate: taxRate })
                }}</span>
                <span>{{ formatPrice(computedTaxAmount) }}</span>
              </div>
              <div class="border-border flex justify-between border-t pt-1.5 font-semibold">
                <span>{{ $t("orderForm.total") }}</span>
                <span>{{ formatPrice(computedTotal) }}</span>
              </div>
            </div>

            <!-- Order notes preview -->
            <div v-if="orderNotes" class="bg-muted rounded-md p-3 text-xs sm:text-sm">
              <span class="font-medium">{{ $t("common.notes") }}:</span> {{ orderNotes }}
            </div>
          </div>

          <div class="mt-4 flex justify-end gap-2">
            <button
              @click="showConfirmDialog = false"
              :disabled="submitting"
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
            >
              {{ $t("common.back") }}
            </button>
            <Button @click="handleSubmitOrder" :disabled="submitting">
              <Icon v-if="submitting" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
              {{ submitting ? $t("common.submitting") : $t("orderForm.submitOrder") }}
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { useOrderCart } from "@/composables/useOrderCart";
import { toast } from "vue-sonner";

const { t } = useI18n();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const router = useRouter();
const client = useSanctumClient();

const products = ref([]);
const info = ref(null);
const loading = ref(true);
const submitting = ref(false);
const showConfirmDialog = ref(false);
const orderNotes = ref("");
const collapsedCategories = ref([]);
const cartPanelRef = ref(null);

const {
  cartItems,
  addItem,
  removeItem,
  updateQuantity,
  updateItemNotes,
  clearCart,
  itemCount,
  subtotal,
  taxAmount,
  total,
} = useOrderCart(route.params.brandEventId);

const taxRate = computed(() => info.value?.tax_rate || 11);
const penaltyRate = computed(() => info.value?.penalty_rate || 0);
const isOnsite = computed(() => penaltyRate.value > 0);

function withPenalty(price) {
  if (!isOnsite.value) return price;
  return Math.round(price * (1 + penaltyRate.value / 100));
}

const penaltyAmount = computed(() => {
  if (!isOnsite.value) return 0;
  return Math.round((subtotal.value * penaltyRate.value) / 100);
});
const subtotalWithPenalty = computed(() => subtotal.value + penaltyAmount.value);
const computedTaxAmount = computed(() =>
  Math.round((subtotalWithPenalty.value * taxRate.value) / 100)
);
const computedTotal = computed(() => subtotalWithPenalty.value + computedTaxAmount.value);

const canOrder = computed(() => {
  if (!info.value) return false;
  // If current_period is set (normal_order or onsite_order), ordering is allowed
  return !!info.value.current_period;
});

const isDeadlinePassed = computed(() => {
  // Used as a guard for add/submit buttons
  return !canOrder.value;
});

function formatDeadline(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  return d.toLocaleDateString(undefined, {
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

async function fetchData() {
  loading.value = true;
  try {
    const [productsRes, infoRes] = await Promise.all([
      client(
        `/api/exhibitor/brands/${route.params.slug}/events/${route.params.brandEventId}/products`
      ),
      client(
        `/api/exhibitor/brands/${route.params.slug}/events/${route.params.brandEventId}/order-form-info`
      ),
    ]);
    products.value = productsRes.data;
    info.value = infoRes.data;
  } catch {
    toast.error(t("orderForm.failedToLoad"));
  } finally {
    loading.value = false;
  }
}

function toggleCategory(name) {
  const index = collapsedCategories.value.indexOf(name);
  if (index >= 0) {
    collapsedCategories.value.splice(index, 1);
  } else {
    collapsedCategories.value.push(name);
  }
}

function getCartItem(productId) {
  return cartItems.value.find((i) => i.event_product_id === productId) || null;
}

const { formatPrice } = useFormatters();

function handleAddToCart(product, categoryName) {
  addItem({ ...product, category: categoryName });
  toast.success(t("orderForm.addedToCart", { name: product.name }));
}

function scrollToCart() {
  cartPanelRef.value?.scrollIntoView({ behavior: "smooth", block: "start" });
}

async function handleSubmitOrder() {
  submitting.value = true;
  try {
    const payload = {
      items: cartItems.value.map((item) => ({
        event_product_id: item.event_product_id,
        quantity: item.quantity,
        notes: item.notes || null,
      })),
      notes: orderNotes.value || null,
    };

    const res = await client(
      `/api/exhibitor/brands/${route.params.slug}/events/${route.params.brandEventId}/orders`,
      {
        method: "POST",
        body: payload,
      }
    );

    clearCart();
    showConfirmDialog.value = false;
    toast.success(t("orderForm.submittedSuccess"));

    router.push(
      `/brands/${route.params.slug}/orders/${route.params.brandEventId}/${res.data.ulid}`
    );
  } catch (error) {
    toast.error(error.response?._data?.message || t("orderForm.failedToSubmit"));
  } finally {
    submitting.value = false;
  }
}

onMounted(fetchData);

usePageMeta(null, {
  title: computed(() => (info.value ? `Order Form · ${info.value.event?.title}` : "Order Form")),
});
</script>
