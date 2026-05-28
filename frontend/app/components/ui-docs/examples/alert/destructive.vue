<script setup>
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
</script>

<template>
  <Alert variant="destructive" class="max-w-md">
    <Icon name="lucide:circle-alert" />
    <AlertTitle>Payment failed</AlertTitle>
    <AlertDescription>Your card was declined. Try a different payment method.</AlertDescription>
  </Alert>
</template>
