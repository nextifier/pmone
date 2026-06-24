<script setup>
import { ref } from "vue";
import { Rating } from "@/components/ui/rating";

const value = ref(3);
</script>

<template>
  <Rating v-model="value" disabled aria-label="Rating" />
</template>
