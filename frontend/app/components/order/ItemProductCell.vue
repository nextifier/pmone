<template>
  <div class="min-w-0">
    <p class="truncate font-medium tracking-tight">{{ item.product_name }}</p>
    <p v-if="item.notes" class="text-muted-foreground mt-0.5 truncate text-sm tracking-tight">
      {{ item.notes }}
    </p>

    <div v-if="adjustments.length" class="mt-1.5 flex flex-wrap items-center gap-1">
      <div
        v-for="adj in adjustments"
        :key="adj.id"
        class="flex items-center gap-x-1"
        :class="adj.is_voided ? 'opacity-50' : ''"
      >
        <Badge :variant="adj.kind === 'discount' ? 'success' : 'warning'" class="px-1.5 py-0.5 text-xs">
          {{ adj.kind_label }}
        </Badge>
        <span class="text-xs tracking-tight tabular-nums">
          {{ adj.kind === "discount" ? "-" : "+" }}{{ formatPrice(adj.amount) }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";

defineProps({
  item: { type: Object, required: true },
  adjustments: { type: Array, default: () => [] },
});

function formatPrice(amount) {
  if (amount == null) {
    return "-";
  }
  return `Rp${Number(amount).toLocaleString("id-ID")}`;
}
</script>
