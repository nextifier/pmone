<script setup>
import { ref } from "vue";
import { RangeCalendarPicker } from "@/components/ui/range-calendar-picker";

const range = ref();
</script>

<template>
  <RangeCalendarPicker v-model="range" class="w-72" />
</template>
