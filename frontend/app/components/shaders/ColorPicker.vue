<script setup>
import { computed } from "vue";
import { Input } from "@/components/ui/input";

// A minimal color control: a native swatch + a free-text hex field. The native
// <input type="color"> only accepts #rrggbb, so the swatch falls back to black
// for non-hex values (named colors, 8-digit hex) while the text field still
// edits the raw value.
const model = defineModel({ type: String, default: "#000000" });

const swatch = computed(() => {
  const v = String(model.value ?? "");
  return /^#[0-9a-fA-F]{6}$/.test(v) ? v : "#000000";
});
</script>

<template>
  <div class="flex items-center gap-x-2">
    <input
      type="color"
      :value="swatch"
      class="border-input size-9 shrink-0 cursor-pointer rounded-md border bg-transparent p-1"
      @input="model = $event.target.value"
    />
    <Input :model-value="model" class="font-mono text-xs sm:text-sm" @update:model-value="model = $event" />
  </div>
</template>
