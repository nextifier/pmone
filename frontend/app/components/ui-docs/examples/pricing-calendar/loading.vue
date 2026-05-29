<script setup>
import { computed, ref } from "vue";
import { CalendarDate, getLocalTimeZone, today as todayFn } from "@internationalized/date";
import { PricingCalendar } from "@/components/ui/pricing-calendar";

const today = todayFn(getLocalTimeZone());
const placeholder = new CalendarDate(today.year, today.month, 1);
const loading = ref(true);

function buildMonth(year, month) {
  const map = {};
  const lastDay = new Date(year, month, 0).getDate();
  const pad = (n) => String(n).padStart(2, "0");
  for (let d = 1; d <= lastDay; d++) {
    map[`${year}-${pad(month)}-${pad(d)}`] = { rate: 1_500_000, available: 8 };
  }
  return map;
}

const pricing = computed(() => (loading.value ? {} : buildMonth(today.year, today.month)));

onMounted(() => {
  setTimeout(() => {
    loading.value = false;
  }, 1800);
});
</script>

<template>
  <PricingCalendar
    :placeholder="placeholder"
    :pricing-data="pricing"
    :is-loading="loading"
    :number-of-months="1"
    :min-value="today"
    class="rounded-md border"
  />
</template>
