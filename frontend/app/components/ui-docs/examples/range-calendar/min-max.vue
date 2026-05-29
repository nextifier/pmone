<script setup>
import { ref } from "vue";
import { getLocalTimeZone, today } from "@internationalized/date";
import { RangeCalendar } from "@/components/ui/range-calendar";

const range = ref();
const start = today(getLocalTimeZone());
const minValue = start.subtract({ days: 3 });
const maxValue = start.add({ days: 21 });
</script>

<template>
  <ClientOnly>
    <RangeCalendar
      v-model="range"
      :min-value="minValue"
      :max-value="maxValue"
      class="rounded-md border"
    />
  </ClientOnly>
</template>
