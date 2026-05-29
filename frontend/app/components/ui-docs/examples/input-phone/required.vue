<script setup>
import { ref } from "vue";
import { InputPhone } from "@/components/ui/input-phone";

const phone = ref("");
</script>

<template>
  <InputPhone v-model="phone" required class="w-full max-w-xs" />
</template>
