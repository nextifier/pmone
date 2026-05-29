<script setup>
import { CardNotch } from "@/components/ui/card-notch";
</script>

<template>
  <CardNotch class="w-full max-w-xs" body-class="p-5 pb-20">
    <template #notch>
      <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
    </template>
    <h3 class="text-base font-medium tracking-tight">Quick action</h3>
    <p class="text-muted-foreground mt-1 text-sm tracking-tight">
      Launch the workflow with a single tap.
    </p>
  </CardNotch>
</template>
