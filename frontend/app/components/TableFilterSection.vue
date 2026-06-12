<template>
  <div class="space-y-2">
    <div class="text-muted-foreground text-xs font-medium tracking-tight">{{ title }}</div>
    <div class="space-y-2">
      <div
        v-for="(option, i) in normalizedOptions"
        :key="option.value"
        class="flex items-center gap-2"
      >
        <Checkbox
          :id="`${title}-${i}`"
          :model-value="selected.includes(option.value)"
          @update:model-value="$emit('change', { checked: !!$event, value: option.value })"
        />
        <Label
          :for="`${title}-${i}`"
          class="grow cursor-pointer font-normal tracking-tight capitalize"
        >
          {{ option.label }}
        </Label>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";

const props = defineProps({
  title: { type: String, required: true },
  options: { type: Array, required: true },
  selected: { type: Array, default: () => [] },
});

defineEmits(["change"]);

const normalizedOptions = computed(() =>
  props.options.map((option) =>
    typeof option === "string" ? { label: option, value: option } : option
  )
);
</script>
