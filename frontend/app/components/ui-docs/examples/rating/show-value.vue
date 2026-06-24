<script setup>
import { ref } from "vue";
import { Rating } from "@/components/ui/rating";

const value = ref(4);
</script>

<template>
  <Rating v-model="value" show-value aria-label="Rating" />
</template>
