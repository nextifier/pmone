<script setup>
import { ref } from "vue";
import { InputNumber } from "@/components/ui/input-number";

const value = ref(9.99);
</script>

<template>
  <InputNumber v-model="value" decimal :min="0" class="w-32" />
</template>
