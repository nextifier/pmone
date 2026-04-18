<template>
  <div v-if="options?.length" class="space-y-3 pt-4">
    <h2 class="text-lg font-semibold tracking-tight">Transfer (Optional)</h2>
    <div v-for="opt in options" :key="opt.id" class="rounded-md border p-4 sm:p-5">
      <label class="flex items-start gap-3 cursor-pointer">
        <Checkbox
          :model-value="!!modelValue[opt.id]"
          class="mt-0.5"
          @update:model-value="toggle(opt.id, $event)"
        />
        <div class="flex-1 min-w-0 space-y-0.5">
          <p class="text-sm font-medium tracking-tight">{{ opt.label }}</p>
          <p v-if="transferMeta(opt)" class="text-muted-foreground text-xs sm:text-sm tracking-tight">
            {{ transferMeta(opt) }}
          </p>
        </div>
        <p class="text-sm font-medium tracking-tight tabular-nums">Rp {{ formatRupiah(opt.price) }}</p>
      </label>
    </div>
  </div>
</template>

<script setup>
import { Checkbox } from "@/components/ui/checkbox";

const props = defineProps({
  options: { type: Array, default: () => [] },
  modelValue: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue"]);

const toggle = (id, value) => {
  emit("update:modelValue", { ...props.modelValue, [id]: !!value });
};

const transferMeta = (opt) => {
  const parts = [];
  if (opt.direction_label) parts.push(opt.direction_label);
  if (opt.vehicle_type) parts.push(opt.vehicle_type);
  if (opt.max_pax) parts.push(`max ${opt.max_pax} pax`);
  return parts.join(" · ");
};

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);
</script>
