<template>
  <div class="space-y-2">
    <div class="text-muted-foreground text-xs font-medium">{{ title }}</div>
    <div class="space-y-2">
      <div v-for="(option, i) in options" :key="getOptionValue(option)" class="flex items-center gap-2">
        <Checkbox
          :id="`${title}-${i}`"
          :model-value="selected.includes(getOptionValue(option))"
          @update:model-value="(checked) => $emit('change', { checked: !!checked, value: getOptionValue(option) })"
        />
        <Label
          :for="`${title}-${i}`"
          class="grow cursor-pointer font-normal tracking-tight capitalize"
        >
          {{ getOptionLabel(option) }}
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

const getOptionValue = (option) => (typeof option === "string" ? option : option.value);
const getOptionLabel = (option) => (typeof option === "string" ? option : option.label);
</script>
