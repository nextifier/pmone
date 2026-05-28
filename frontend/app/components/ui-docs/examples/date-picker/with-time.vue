<script setup>
import { ref } from "vue";
import { DatePicker } from "@/components/ui/date-picker";

const datetime = ref();
</script>

<template>
  <DatePicker v-model="datetime" with-time placeholder="Pick a date and time" class="w-72" />
</template>
