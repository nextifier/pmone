<script setup>
import { ref } from "vue";
import { DatePicker } from "@/components/ui/date-picker";

const date = ref();
</script>

<template>
  <DatePicker v-model="date" disabled class="w-60" />
</template>
