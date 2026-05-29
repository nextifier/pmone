<script setup>
import { CardNotch } from "@/components/ui/card-notch";
</script>

<template>
  <div class="grid w-full grid-cols-1 items-start gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <CardNotch v-for="i in 3" :key="i" body-class="p-5">
      <h3 class="text-base font-medium tracking-tight">Acme Inc</h3>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">
        Plain rounded card, no notch.
      </p>
    </CardNotch>
  </div>
</template>
