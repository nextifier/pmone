<script setup>
import { ref } from "vue";
import { InputOTP, InputOTPGroup, InputOTPSlot } from "@/components/ui/input-otp";

const code = ref("");
</script>

<template>
  <InputOTP v-model="code" :maxlength="6">
    <InputOTPGroup>
      <InputOTPSlot v-for="i in 6" :key="i" :index="i - 1" />
    </InputOTPGroup>
  </InputOTP>
</template>
