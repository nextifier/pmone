<script setup lang="ts">
import { Switch } from "@/components/ui/switch";

const props = defineProps<{
  modelValue: boolean;
  disabled?: boolean;
  itemId: string | number;
  statusKey?: string;
}>();

const emit = defineEmits<{
  "update:modelValue": [value: boolean];
}>();

// Use a global store to track switch states that can be updated externally
const switchStates = useState<Record<string, boolean>>("table-switch-states", () => ({}));

// Initialize state from props
const stateKey = computed(() => `${props.statusKey || "default"}-${props.itemId}`);

// Set initial value if not exists
if (switchStates.value[stateKey.value] === undefined) {
  switchStates.value[stateKey.value] = props.modelValue;
}

// Watch for external prop changes
watch(
  () => props.modelValue,
  (newVal) => {
    switchStates.value[stateKey.value] = newVal;
  }
);

const checked = computed(() => switchStates.value[stateKey.value] ?? props.modelValue);

const handleChange = (value: boolean) => {
  switchStates.value[stateKey.value] = value;
  emit("update:modelValue", value);
};
</script>

<template>
  <Switch :model-value="checked" :disabled="disabled" @update:model-value="handleChange" />
</template>
