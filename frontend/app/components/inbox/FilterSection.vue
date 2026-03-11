<template>
  <div class="space-y-2">
    <div class="text-muted-foreground text-xs font-medium">{{ title }}</div>
    <div class="space-y-2">
      <div v-for="(option, i) in options" :key="optionValue(option)" class="flex items-center gap-2">
        <Checkbox
          :id="`${title}-${i}`"
          :modelValue="selected.includes(optionValue(option))"
          @update:modelValue="(checked) => $emit('change', { checked: !!checked, value: optionValue(option) })"
        />
        <Label
          :for="`${title}-${i}`"
          class="grow cursor-pointer font-normal tracking-tight capitalize"
        >
          {{ optionLabel(option) }}
        </Label>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";

defineProps({
  title: { type: String, required: true },
  options: { type: Array, required: true },
  selected: { type: Array, default: () => [] },
});

defineEmits(["change"]);

function optionValue(option) {
  return typeof option === "string" ? option : option.value;
}

function optionLabel(option) {
  return typeof option === "string" ? option : option.label;
}
</script>
