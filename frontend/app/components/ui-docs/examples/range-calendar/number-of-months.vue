<script setup>
import { ref } from "vue";
import { RangeCalendar } from "@/components/ui/range-calendar";

const range = ref();
</script>

<template>
  <RangeCalendar v-model="range" :number-of-months="2" class="rounded-md border" />
</template>
