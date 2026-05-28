<script setup>
import { ref } from "vue";
import { Slider } from "@/components/ui/slider";

const range = ref([20, 80]);
</script>

<template>
  <Slider v-model="range" :max="100" :step="1" class="w-full max-w-sm" />
</template>
