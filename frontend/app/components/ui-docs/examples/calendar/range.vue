<script setup>
import { ref } from "vue";
import { RangeCalendar } from "@/components/ui/range-calendar";

const range = ref();
</script>

<template>
  <RangeCalendar v-model="range" class="rounded-md border" />
</template>
