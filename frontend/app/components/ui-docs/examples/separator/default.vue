<script setup>
import { Separator } from "@/components/ui/separator";
</script>

<template>
  <div class="w-full max-w-md space-y-3">
    <div>
      <h4 class="text-sm font-semibold tracking-tight">Account</h4>
      <p class="text-muted-foreground text-sm tracking-tight">Personal details and password.</p>
    </div>
    <Separator />
    <div>
      <h4 class="text-sm font-semibold tracking-tight">Billing</h4>
      <p class="text-muted-foreground text-sm tracking-tight">Plan, invoices, payment methods.</p>
    </div>
  </div>
</template>
