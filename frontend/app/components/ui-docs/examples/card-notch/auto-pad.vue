<script setup>
import { CardNotch } from "@/components/ui/card-notch";
</script>

<template>
  <div class="grid w-full grid-cols-1 items-start gap-3 sm:grid-cols-3">
    <CardNotch v-for="np in ['0px', '0.75rem', '1.5rem']" :key="np" auto-pad :notch-padding="np" body-class="p-5">
      <template #notch>
        <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
      </template>
      <h3 class="text-base font-medium tracking-tight">notch-padding {{ np }}</h3>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">
        Card reserves notch space.
      </p>
    </CardNotch>
  </div>
</template>
