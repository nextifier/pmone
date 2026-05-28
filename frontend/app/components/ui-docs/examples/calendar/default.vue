<script setup>
import { ref } from "vue";
import { Calendar } from "@/components/ui/calendar";

const value = ref();
</script>

<template>
  <Calendar v-model="value" class="rounded-md border" />
</template>
