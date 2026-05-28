<script setup>
import { ref } from "vue";
import { InputNumber } from "@/components/ui/input-number";

const value = ref(10);
</script>

<template>
  <InputNumber v-model="value" :min="0" :max="100" :step="5" class="w-32" />
</template>
