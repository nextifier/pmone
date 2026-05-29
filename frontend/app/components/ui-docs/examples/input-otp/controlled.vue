<script setup>
import { ref } from "vue";
import { InputOTP, InputOTPGroup, InputOTPSlot } from "@/components/ui/input-otp";

const code = ref("");
</script>

<template>
  <div class="flex flex-col items-center gap-3">
    <InputOTP v-model="code" :maxlength="6">
      <InputOTPGroup>
        <InputOTPSlot v-for="i in 6" :key="i" :index="i - 1" />
      </InputOTPGroup>
    </InputOTP>
    <p class="text-muted-foreground text-sm tracking-tight">
      Entered: {{ code || "nothing yet" }}
    </p>
  </div>
</template>
