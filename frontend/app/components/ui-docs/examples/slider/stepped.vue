<script setup>
import { ref } from "vue";
import { Slider } from "@/components/ui/slider";

const value = ref([25]);
</script>

<template>
  <Slider v-model="value" :max="100" :step="25" class="w-full max-w-sm" />
</template>
