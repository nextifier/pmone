<template>
  <Select
    class="focus-visible:outline-hidden"
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <SelectTrigger data-size="sm" class="w-40">
      <SelectValue placeholder="Select date range" />
    </SelectTrigger>
    <SelectContent>
      <SelectItem value="today">Today</SelectItem>
      <SelectItem value="yesterday">Yesterday</SelectItem>
      <SelectItem value="this_week">This week</SelectItem>
      <SelectItem value="7">Last 7 days</SelectItem>
      <SelectItem value="last_week">Last week</SelectItem>
      <SelectItem value="30">Last 30 days</SelectItem>
      <SelectItem value="this_month">This month</SelectItem>
      <SelectItem value="last_month">Last month</SelectItem>
      <SelectItem value="90">Last 90 days</SelectItem>
      <SelectItem value="this_year">This year</SelectItem>
      <SelectItem value="365">Last 365 days</SelectItem>
    </SelectContent>
  </Select>
</template>

<script setup>
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

defineProps({
  modelValue: {
    type: String,
    required: true,
  },
});

defineEmits(["update:modelValue"]);
</script>
