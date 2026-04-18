<template>
  <div class="grid grid-cols-2 gap-3">
    <div class="space-y-2">
      <Label>Check-in</Label>
      <DatePicker
        :model-value="checkIn"
        placeholder="Select check-in"
        disable-past-dates
        @update:model-value="$emit('update:checkIn', $event)"
      />
    </div>
    <div class="space-y-2">
      <Label>Check-out</Label>
      <DatePicker
        :model-value="checkOut"
        placeholder="Select check-out"
        disable-past-dates
        @update:model-value="$emit('update:checkOut', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { DatePicker } from "@/components/ui/date-picker";
import { Label } from "@/components/ui/label";

defineProps({
  checkIn: { type: Date, default: null },
  checkOut: { type: Date, default: null },
});

defineEmits(["update:checkIn", "update:checkOut"]);
</script>
