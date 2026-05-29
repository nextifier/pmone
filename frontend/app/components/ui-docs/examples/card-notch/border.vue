<script setup>
import { CardNotch } from "@/components/ui/card-notch";
</script>

<template>
  <div class="grid w-full grid-cols-1 items-start gap-3 sm:grid-cols-3">
    <CardNotch border-width="2px" body-class="p-5 pb-20">
      <template #notch>
        <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
      </template>
      <h3 class="text-base font-medium tracking-tight">Thicker</h3>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">border-width 2px</p>
    </CardNotch>
    <CardNotch border-color="var(--color-destructive)" body-class="p-5 pb-20">
      <template #notch>
        <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
      </template>
      <h3 class="text-base font-medium tracking-tight">Destructive</h3>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">border-color destructive</p>
    </CardNotch>
    <CardNotch border-width="3px" border-color="var(--color-info)" body-class="p-5 pb-20">
      <template #notch>
        <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
      </template>
      <h3 class="text-base font-medium tracking-tight">Combined</h3>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">3px info colour</p>
    </CardNotch>
  </div>
</template>
