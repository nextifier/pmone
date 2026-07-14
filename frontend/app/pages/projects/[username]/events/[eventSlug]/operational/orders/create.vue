<template>
  <div class="pb-16">
    <div class="mb-6 flex flex-col items-start gap-y-3">
      <ButtonBack :show-label="true" />
    </div>

    <div class="space-y-1">
      <h1 class="text-2xl font-semibold tracking-tighter">Create Order</h1>
      <p class="text-muted-foreground text-sm tracking-tight">
        Place an order on behalf of an exhibitor.
      </p>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-3">
      <!-- Form (2/3) -->
      <div class="space-y-6 lg:col-span-2">
        <!-- Brand selector -->
        <div class="space-y-2">
          <Label>Exhibitor Brand</Label>
          <Combobox v-model="selectedBrandEventId" :ignore-filter="true" :open-on-focus="true">
            <ComboboxAnchor as-child class="w-full">
              <div
                class="border-border relative flex h-9 w-full items-center rounded-md border shadow-xs"
              >
                <ComboboxInputPrimitive
                  v-model="brandSearch"
                  :display-value="() => selectedBrandEvent?.brand_name || ''"
                  placeholder="Select a brand..."
                  class="placeholder:text-muted-foreground h-full w-full rounded-md bg-transparent px-3 text-sm tracking-tight outline-none"
                  autocomplete="off"
                />
                <Icon
                  name="lucide:chevrons-up-down"
                  class="text-muted-foreground absolute right-2 size-4 shrink-0"
                />
              </div>
            </ComboboxAnchor>
            <ComboboxList class="w-[var(--reka-combobox-trigger-width)]">
              <ComboboxViewport class="max-h-72 p-1">
                <ComboboxEmpty>No brand found.</ComboboxEmpty>
                <ComboboxItem
                  v-for="be in filteredBrandEvents"
                  :key="be.id"
                  :value="be.id"
                  class="data-highlighted:bg-muted flex w-full cursor-default items-center gap-2 rounded-md px-2 py-2 outline-none select-none"
                >
                  <Avatar
                    :model="{ name: be.brand_name, profile_image: be.profile_image }"
                    class="size-7"
                    rounded="rounded-md"
                  />
                  <div class="min-w-0">
                    <p class="truncate text-sm font-medium tracking-tight">{{ be.brand_name }}</p>
                    <p v-if="be.booth_number" class="text-muted-foreground text-sm tracking-tight">
                      Booth {{ be.booth_number }}
                    </p>
                  </div>
                  <ComboboxItemIndicator class="ml-auto">
                    <Icon name="hugeicons:tick-02" class="size-4 shrink-0" />
                  </ComboboxItemIndicator>
                </ComboboxItem>
              </ComboboxViewport>
            </ComboboxList>
          </Combobox>
        </div>

        <!-- Catalog -->
        <div v-if="infoLoading" class="space-y-3">
          <div v-for="i in 3" :key="i" class="bg-muted h-24 animate-pulse rounded-lg" />
        </div>

        <OrderProductPicker
          v-else-if="info"
          v-model="items"
          :products-by-category="info.products_by_category"
          :currency="info?.currency ?? 'IDR'"
        />

        <!-- Order-level fields -->
        <div v-if="info" class="space-y-4">
          <div class="space-y-2">
            <Label for="order_notes">Order Notes</Label>
            <Textarea
              id="order_notes"
              v-model="orderNotes"
              placeholder="Notes visible to the exhibitor (optional)"
              rows="3"
            />
          </div>
          <div class="space-y-2">
            <Label for="internal_notes">Internal Notes</Label>
            <Textarea
              id="internal_notes"
              v-model="internalNotes"
              placeholder="Staff-only notes (optional)"
              rows="3"
            />
          </div>
          <div class="space-y-2">
            <Label for="promo_code">Promo Code</Label>
            <Input id="promo_code" v-model="promoCode" placeholder="Optional" />
          </div>
        </div>
      </div>

      <!-- Summary (1/3) -->
      <div class="lg:col-span-1">
        <div class="sticky top-[var(--navbar-height-desktop)] space-y-4">
          <div v-if="info" class="flex items-center justify-end">
            <Badge variant="outline">Currency: {{ info?.currency ?? "IDR" }}</Badge>
          </div>

          <OrderSummaryPanel
            :items="items"
            :tax-rate="Number(info?.tax_rate ?? 11)"
            :penalty-rate="Number(info?.penalty_rate ?? 0)"
            :currency="info?.currency ?? 'IDR'"
          />

          <div v-if="info" class="space-y-3">
            <div class="flex items-start gap-x-2">
              <Checkbox id="send_email" v-model="sendConfirmationEmail" />
              <Label for="send_email" class="text-sm font-normal leading-snug">
                Send confirmation email to exhibitor
              </Label>
            </div>

            <Button class="w-full" :disabled="!canSubmit || submitting" @click="submit">
              <Spinner v-if="submitting" class="mr-1.5 size-4" />
              Create Order
            </Button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Combobox,
  ComboboxAnchor,
  ComboboxEmpty,
  ComboboxItem,
  ComboboxItemIndicator,
  ComboboxList,
  ComboboxViewport,
} from "@/components/ui/combobox";
import { ComboboxInput as ComboboxInputPrimitive } from "reka-ui";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "vue-sonner";

usePageMeta(null, { title: "Create Order" });

const route = useRoute();
const router = useRouter();
const client = useSanctumClient();

const ordersBase = computed(
  () => `/api/projects/${route.params.username}/events/${route.params.eventSlug}/orders`
);

const brandEvents = ref([]);
const selectedBrandEventId = ref(null);
const brandSearch = ref("");
const info = ref(null);
const infoLoading = ref(false);
const items = ref([]);
const orderNotes = ref("");
const internalNotes = ref("");
const promoCode = ref("");
const sendConfirmationEmail = ref(true);
const submitting = ref(false);

const selectedBrandEvent = computed(() =>
  brandEvents.value.find((be) => be.id === selectedBrandEventId.value)
);

const filteredBrandEvents = computed(() => {
  const term = brandSearch.value.trim().toLowerCase();
  if (!term) return brandEvents.value;
  return brandEvents.value.filter(
    (be) =>
      be.brand_name?.toLowerCase().includes(term) ||
      String(be.booth_number || "").toLowerCase().includes(term)
  );
});

const canSubmit = computed(() => !!selectedBrandEventId.value && items.value.length > 0);

async function fetchBrandEvents() {
  try {
    const res = await client(
      `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands`,
      { params: { per_page: 200 } }
    );
    brandEvents.value = res.data || [];
  } catch (e) {
    toast.error("Failed to load brands");
  }
}

watch(selectedBrandEventId, async (id) => {
  info.value = null;
  items.value = [];
  if (!id) return;

  infoLoading.value = true;
  try {
    const res = await client(`${ordersBase.value}/create-info`, {
      params: { brand_event_id: id },
    });
    info.value = res.data;
  } catch (e) {
    toast.error("Failed to load order form");
  } finally {
    infoLoading.value = false;
  }
});

async function submit() {
  if (!canSubmit.value) return;
  submitting.value = true;
  try {
    const res = await client(ordersBase.value, {
      method: "POST",
      body: {
        brand_event_id: selectedBrandEventId.value,
        items: items.value.map((i) => ({
          event_product_id: i.event_product_id,
          quantity: i.quantity,
          notes: i.notes || null,
        })),
        notes: orderNotes.value || null,
        internal_notes: internalNotes.value || null,
        promo_code: promoCode.value || null,
        send_confirmation_email: sendConfirmationEmail.value,
      },
    });
    toast.success("Order created");
    router.push(
      `/projects/${route.params.username}/events/${route.params.eventSlug}/operational/orders/${res.data.ulid}`
    );
  } catch (e) {
    toast.error(e?.data?.message || "Failed to create order");
  } finally {
    submitting.value = false;
  }
}

onMounted(fetchBrandEvents);
</script>
