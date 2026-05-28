<script setup>
import { ref } from "vue";
import { PricingCalendar } from "@/components/ui/pricing-calendar";

const date = ref();
const prices = {
  "2026-06-01": 120,
  "2026-06-02": 120,
  "2026-06-03": 145,
  "2026-06-04": 145,
  "2026-06-05": 180,
  "2026-06-06": 220,
  "2026-06-07": 220,
};
</script>

<template>
  <PricingCalendar v-model="date" :prices="prices" currency="USD" class="rounded-md border" />
</template>
