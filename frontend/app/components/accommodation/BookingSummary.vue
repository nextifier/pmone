<template>
  <div class="rounded-lg border p-5 space-y-4 sticky top-4">
    <h3 class="text-base font-semibold tracking-tight">Booking</h3>

    <DateRangeSelector
      :check-in="checkIn"
      :check-out="checkOut"
      @update:check-in="$emit('update:checkIn', $event)"
      @update:check-out="$emit('update:checkOut', $event)"
    />

    <div class="text-xs sm:text-sm tracking-tight space-y-1.5 border-t pt-3">
      <div class="flex justify-between">
        <span>Subtotal rooms</span>
        <span>Rp {{ formatRupiah(summary.rooms) }}</span>
      </div>
      <div class="flex justify-between">
        <span>Transfer</span>
        <span>Rp {{ formatRupiah(summary.transfer) }}</span>
      </div>
      <div class="flex justify-between text-muted-foreground">
        <span>Tax {{ taxPercentage }}%</span>
        <span>Rp {{ formatRupiah(summary.tax) }}</span>
      </div>
      <div v-if="summary.service > 0" class="flex justify-between text-muted-foreground">
        <span>Service {{ servicePercentage }}%</span>
        <span>Rp {{ formatRupiah(summary.service) }}</span>
      </div>
      <div class="flex justify-between font-semibold pt-1.5 border-t">
        <span>Total</span>
        <span>Rp {{ formatRupiah(summary.total) }}</span>
      </div>
    </div>

    <Button class="w-full" :disabled="!canProceed" @click="$emit('continue')">
      Continue to Booking
    </Button>
  </div>
</template>

<script setup>
import DateRangeSelector from "./DateRangeSelector.vue";
import { Button } from "@/components/ui/button";

defineProps({
  checkIn: { type: Date, default: null },
  checkOut: { type: Date, default: null },
  summary: { type: Object, required: true },
  taxPercentage: { type: [Number, String], default: 0 },
  servicePercentage: { type: [Number, String], default: 0 },
  canProceed: { type: Boolean, default: false },
});

defineEmits(["update:checkIn", "update:checkOut", "continue"]);

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);
</script>
