<script setup>
import { CardNotch } from "@/components/ui/card-notch";

const positions = [
  { value: "top-left", bodyClass: "p-5 pt-20" },
  { value: "top-center", bodyClass: "p-5 pt-20 text-center" },
  { value: "top-right", bodyClass: "p-5 pt-20 text-right" },
  { value: "bottom-left", bodyClass: "p-5 pb-20" },
  { value: "bottom-center", bodyClass: "p-5 pb-20 text-center" },
  { value: "bottom-right", bodyClass: "p-5 pb-20 text-right" },
];
</script>

<template>
  <div class="grid w-full grid-cols-1 items-start gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <CardNotch v-for="p in positions" :key="p.value" :position="p.value" :body-class="p.bodyClass">
      <template #notch>
        <Icon name="hugeicons:arrow-up-right-03" class="size-4" />
      </template>
      <p class="text-sm font-medium tracking-tight">{{ p.value }}</p>
    </CardNotch>
  </div>
</template>
