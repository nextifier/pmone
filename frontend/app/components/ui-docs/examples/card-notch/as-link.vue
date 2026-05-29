<script setup>
import { ref } from "vue";
import { CardNotch } from "@/components/ui/card-notch";

const clicks = ref(0);
</script>

<template>
  <div class="grid w-full grid-cols-1 items-start gap-3 sm:grid-cols-3">
    <CardNotch to="/ui/card-notch" body-class="p-5 pb-20">
      <template #notch>
        <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
      </template>
      <h3 class="text-base font-medium tracking-tight">Internal route</h3>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">to="/ui/card-notch"</p>
    </CardNotch>
    <CardNotch href="https://nuxt.com" body-class="p-5 pb-20">
      <template #notch>
        <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
      </template>
      <h3 class="text-base font-medium tracking-tight">External link</h3>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">opens in a new tab</p>
    </CardNotch>
    <CardNotch as="button" body-class="p-5 pb-20" @click="clicks++">
      <template #notch>
        <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
      </template>
      <h3 class="text-base font-medium tracking-tight">As button</h3>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">clicked {{ clicks }}x</p>
    </CardNotch>
  </div>
</template>
