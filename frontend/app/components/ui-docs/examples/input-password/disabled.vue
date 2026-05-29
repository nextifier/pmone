<script setup>
import { ref } from "vue";
import { InputPassword } from "@/components/ui/input-password";

const value = ref("hunter2");
</script>

<template>
  <InputPassword v-model="value" disabled class="w-full max-w-xs" />
</template>
