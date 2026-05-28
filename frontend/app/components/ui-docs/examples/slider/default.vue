<script setup>
import { ref } from "vue";
import { Slider } from "@/components/ui/slider";

const value = ref([50]);
</script>

<template>
  <Slider v-model="value" :max="100" :step="1" class="w-full max-w-sm" />
</template>
