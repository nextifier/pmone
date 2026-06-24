<script setup>
import { ref } from "vue";
import { Rating } from "@/components/ui/rating";

const value = ref(2.5);
</script>

<template>
  <Rating v-model="value" allow-half show-value aria-label="Rating with halves" />
</template>
