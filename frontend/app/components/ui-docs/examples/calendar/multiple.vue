<script setup>
import { ref } from "vue";
import { Calendar } from "@/components/ui/calendar";

const dates = ref([]);
</script>

<template>
  <Calendar v-model="dates" multiple class="rounded-md border" />
</template>
