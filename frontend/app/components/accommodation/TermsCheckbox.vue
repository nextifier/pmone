<template>
  <label class="flex items-start gap-2 text-sm tracking-tight pt-2">
    <Checkbox
      :model-value="modelValue"
      required
      @update:model-value="$emit('update:modelValue', $event)"
    />
    <span>
      I agree to the
      <NuxtLink to="/terms" target="_blank" class="text-primary underline">terms & conditions</NuxtLink>
      and
      <NuxtLink to="/privacy" target="_blank" class="text-primary underline">privacy policy</NuxtLink>.
    </span>
  </label>
</template>

<script setup>
import { Checkbox } from "@/components/ui/checkbox";

defineProps({
  modelValue: { type: Boolean, default: false },
});

defineEmits(["update:modelValue"]);
</script>
