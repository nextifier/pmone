<script setup>
import { ref } from "vue";
import { InputPassword } from "@/components/ui/input-password";

const value = ref("");
</script>

<template>
  <InputPassword v-model="value" placeholder="Password" class="w-full max-w-xs" />
</template>
