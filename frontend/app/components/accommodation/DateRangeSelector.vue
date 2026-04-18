<template>
  <div class="grid grid-cols-2 gap-3">
    <div class="space-y-2">
      <Label>Check-in</Label>
      <Input
        :model-value="checkIn"
        type="date"
        :min="minCheckIn"
        required
        @update:model-value="$emit('update:checkIn', $event)"
      />
    </div>
    <div class="space-y-2">
      <Label>Check-out</Label>
      <Input
        :model-value="checkOut"
        type="date"
        :min="checkIn || minCheckIn"
        required
        @update:model-value="$emit('update:checkOut', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

defineProps({
  checkIn: { type: String, default: "" },
  checkOut: { type: String, default: "" },
  minCheckIn: { type: String, default: "" },
});

defineEmits(["update:checkIn", "update:checkOut"]);
</script>
