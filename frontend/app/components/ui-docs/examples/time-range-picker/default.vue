<script setup>
import { ref } from "vue";
import { Time } from "@internationalized/date";
import { TimeRangePicker } from "@/components/ui/time-range-picker";

const range = ref({ start: new Time(9, 0), end: new Time(17, 0) });
</script>

<template>
  <ClientOnly>
    <TimeRangePicker v-model="range" />
  </ClientOnly>
</template>
