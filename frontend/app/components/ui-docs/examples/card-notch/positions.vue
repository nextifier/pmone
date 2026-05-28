<script setup>
import { CardNotch } from "@/components/ui/card-notch";

const positions = [
  "top-left",
  "top-center",
  "top-right",
  "bottom-left",
  "bottom-center",
  "bottom-right",
];
</script>

<template>
  <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <CardNotch v-for="p in positions" :key="p" :position="p" bordered>
      <template #notch>
        <Icon name="lucide:circle" class="size-4" />
      </template>
      <div class="p-5">
        <p class="text-sm tracking-tight">{{ p }}</p>
      </div>
    </CardNotch>
  </div>
</template>
