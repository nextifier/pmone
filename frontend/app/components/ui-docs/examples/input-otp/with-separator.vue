<script setup>
import { ref } from "vue";
import {
  InputOTP,
  InputOTPGroup,
  InputOTPSeparator,
  InputOTPSlot,
} from "@/components/ui/input-otp";

const code = ref("");
</script>

<template>
  <InputOTP v-model="code" :maxlength="6">
    <InputOTPGroup>
      <InputOTPSlot v-for="i in 3" :key="i" :index="i - 1" />
    </InputOTPGroup>
    <InputOTPSeparator />
    <InputOTPGroup>
      <InputOTPSlot v-for="i in 3" :key="i" :index="i + 2" />
    </InputOTPGroup>
  </InputOTP>
</template>
