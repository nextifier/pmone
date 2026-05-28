<script setup>
import { ref } from "vue";
import { InputNumber } from "@/components/ui/input-number";

const qty = ref(1);
</script>

<template>
  <InputNumber v-model="qty" class="w-32" />
</template>
