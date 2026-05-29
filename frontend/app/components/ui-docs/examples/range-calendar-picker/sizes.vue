<script setup>
import { ref } from "vue";
import { RangeCalendarPicker } from "@/components/ui/range-calendar-picker";

const small = ref();
const medium = ref();
const large = ref();
</script>

<template>
  <div class="flex flex-col items-start gap-3">
    <RangeCalendarPicker v-model="small" size="sm" class="w-72" />
    <RangeCalendarPicker v-model="medium" size="default" class="w-72" />
    <RangeCalendarPicker v-model="large" size="lg" class="w-72" />
  </div>
</template>
