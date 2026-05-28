<script setup>
import { Input } from "@/components/ui/input";
import { InputErrorMessage } from "@/components/ui/input-error-message";
</script>

<template>
  <div class="flex w-full max-w-xs flex-col gap-1.5">
    <Input aria-invalid="true" placeholder="Email" class="border-destructive" />
    <InputErrorMessage>Email is required.</InputErrorMessage>
  </div>
</template>
