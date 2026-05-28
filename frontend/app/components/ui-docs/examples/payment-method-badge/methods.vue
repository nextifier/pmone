<script setup>
import { PaymentMethodBadge } from "@/components/ui/payment-method-badge";

const methods = ["visa", "mastercard", "amex", "jcb", "bca", "mandiri", "bni", "bri"];
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <PaymentMethodBadge v-for="m in methods" :key="m" :method="m" />
  </div>
</template>
