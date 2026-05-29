<script setup>
import { ref } from "vue";
import { RangeCalendarPicker } from "@/components/ui/range-calendar-picker";

const range = ref();
</script>

<template>
  <RangeCalendarPicker v-model="range" :number-of-months="1" class="w-72" />
</template>
