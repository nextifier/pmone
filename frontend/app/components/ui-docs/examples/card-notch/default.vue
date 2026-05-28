<script setup>
import { CardNotch } from "@/components/ui/card-notch";
</script>

<template>
  <CardNotch class="w-full max-w-xs" bordered>
    <template #notch>
      <Icon name="lucide:zap" class="size-5" />
    </template>
    <div class="p-5">
      <h3 class="text-base font-semibold tracking-tight">Quick action</h3>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">
        Launch the workflow with a single tap.
      </p>
    </div>
  </CardNotch>
</template>
