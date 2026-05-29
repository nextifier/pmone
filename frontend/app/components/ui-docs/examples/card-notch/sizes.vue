<script setup>
import { CardNotch } from "@/components/ui/card-notch";
</script>

<template>
  <div class="grid w-full grid-cols-1 items-start gap-3 sm:grid-cols-3">
    <CardNotch size="5rem" gap="4px" body-class="p-5 pb-28">
      <template #notch>
        <Icon name="hugeicons:arrow-up-right-03" class="size-7" />
      </template>
      <h3 class="text-base font-medium tracking-tight">Large</h3>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">5rem notch, 4px gap</p>
    </CardNotch>
    <CardNotch size="2.5rem" gap="2px" body-class="p-5 pb-14">
      <template #notch>
        <Icon name="hugeicons:arrow-up-right-03" class="size-4" />
      </template>
      <h3 class="text-base font-medium tracking-tight">Small</h3>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">2.5rem notch, 2px gap</p>
    </CardNotch>
    <CardNotch gap="12px" radius="1rem" body-class="p-5 pb-20">
      <template #notch>
        <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
      </template>
      <h3 class="text-base font-medium tracking-tight">Wide gap</h3>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">12px gap, 1rem radius</p>
    </CardNotch>
  </div>
</template>
