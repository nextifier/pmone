<script setup>
import { computed, ref } from "vue";
import { CalendarDate, getLocalTimeZone, today as todayFn } from "@internationalized/date";
import { PricingCalendar } from "@/components/ui/pricing-calendar";

const today = todayFn(getLocalTimeZone());
const placeholder = new CalendarDate(today.year, today.month, 1);
const range = ref({ start: undefined, end: undefined });

function buildMonth(year, month) {
  const map = {};
  const lastDay = new Date(year, month, 0).getDate();
  const pad = (n) => String(n).padStart(2, "0");
  for (let d = 1; d <= lastDay; d++) {
    const dow = new Date(year, month - 1, d).getDay();
    const weekend = dow === 0 || dow === 6;
    map[`${year}-${pad(month)}-${pad(d)}`] = { rate: weekend ? 1_850_000 : 1_500_000, available: 8 };
  }
  return map;
}

const pricing = computed(() => buildMonth(today.year, today.month));
</script>

<template>
  <PricingCalendar
    v-model="range"
    :placeholder="placeholder"
    :pricing-data="pricing"
    :number-of-months="1"
    :min-value="today"
    class="rounded-md border"
  />
</template>
