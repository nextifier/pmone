<template>
  <div class="space-y-6">
    <template v-if="productsByCategory.length">
      <div
        v-for="category in productsByCategory"
        :key="category.category"
        class="space-y-3"
      >
        <h3 class="text-base font-semibold tracking-tight">
          {{ category.category }}
          <span class="text-muted-foreground font-normal">({{ category.products.length }})</span>
        </h3>

        <div class="space-y-2">
          <div
            v-for="product in category.products"
            :key="product.id"
            class="border-border rounded-lg border p-4"
          >
            <div class="flex items-start justify-between gap-x-4">
              <div class="flex min-w-0 flex-1 items-start gap-x-3">
                <img
                  v-if="product.product_image?.sm"
                  :src="product.product_image.sm"
                  :alt="product.name"
                  class="size-12 shrink-0 rounded-lg object-cover"
                />
                <div class="min-w-0">
                  <p class="font-medium tracking-tight">{{ product.name }}</p>
                  <p
                    v-if="product.description"
                    class="text-muted-foreground mt-0.5 text-sm tracking-tight"
                  >
                    {{ product.description }}
                  </p>
                  <p class="mt-1 text-sm font-medium">
                    {{ formatPrice(product.price) }}
                    <span class="text-muted-foreground font-normal">/ {{ product.unit }}</span>
                  </p>
                </div>
              </div>

              <div class="shrink-0">
                <div v-if="getItem(product.id)" class="flex items-center gap-x-1">
                  <button
                    type="button"
                    class="border-border hover:bg-muted flex size-7 items-center justify-center rounded-md border text-sm transition"
                    @click="setQuantity(product, getItem(product.id).quantity - 1)"
                  >
                    <Icon name="lucide:minus" class="size-3.5" />
                  </button>
                  <span class="w-8 text-center text-sm">{{ getItem(product.id).quantity }}</span>
                  <button
                    type="button"
                    class="border-border hover:bg-muted flex size-7 items-center justify-center rounded-md border text-sm transition"
                    @click="setQuantity(product, getItem(product.id).quantity + 1)"
                  >
                    <Icon name="lucide:plus" class="size-3.5" />
                  </button>
                </div>
                <Button v-else size="sm" variant="outline" @click="setQuantity(product, 1)">
                  <Icon name="lucide:plus" class="mr-1 size-3.5" />
                  Add
                </Button>
              </div>
            </div>

            <!-- Per-item notes -->
            <Textarea
              v-if="getItem(product.id)"
              :model-value="getItem(product.id).notes"
              placeholder="Notes for this item (optional)"
              rows="2"
              class="mt-3 text-sm"
              @update:model-value="(v) => setNotes(product.id, v)"
            />
          </div>
        </div>
      </div>
    </template>

    <p v-else class="text-muted-foreground py-10 text-center text-sm tracking-tight">
      No products available for this booth type.
    </p>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";

const props = defineProps({
  productsByCategory: {
    type: Array,
    default: () => [],
  },
  modelValue: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["update:modelValue"]);

function getItem(productId) {
  return props.modelValue.find((i) => i.event_product_id === productId);
}

function setQuantity(product, quantity) {
  const items = [...props.modelValue];
  const index = items.findIndex((i) => i.event_product_id === product.id);

  if (quantity <= 0) {
    if (index !== -1) items.splice(index, 1);
  } else if (index === -1) {
    items.push({
      event_product_id: product.id,
      name: product.name,
      price: product.price,
      quantity,
      notes: "",
    });
  } else {
    items[index] = { ...items[index], quantity };
  }

  emit("update:modelValue", items);
}

function setNotes(productId, notes) {
  const items = props.modelValue.map((i) =>
    i.event_product_id === productId ? { ...i, notes } : i
  );
  emit("update:modelValue", items);
}

function formatPrice(price) {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    minimumFractionDigits: 0,
  }).format(price || 0);
}
</script>
