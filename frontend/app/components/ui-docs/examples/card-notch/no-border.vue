<script setup>
import { CardNotch } from "@/components/ui/card-notch";
</script>

<template>
  <div class="from-info/15 via-primary/15 to-destructive/15 grid w-full grid-cols-1 items-start gap-3 rounded-xl bg-linear-to-r p-6 sm:grid-cols-3">
    <CardNotch v-for="i in 3" :key="i" :bordered="false" body-class="p-5 pb-20">
      <template #notch>
        <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
      </template>
      <h3 class="text-base font-medium tracking-tight">No border</h3>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">
        The gap stays transparent.
      </p>
    </CardNotch>
  </div>
</template>
