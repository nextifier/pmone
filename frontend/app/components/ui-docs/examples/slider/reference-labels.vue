<script setup>
import { ref } from "vue";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";

const value = ref([15]);
</script>

<template>
  <div class="w-full max-w-sm space-y-4">
    <Label>Slider with reference labels</Label>
    <div>
      <Slider v-model="value" :min="5" :max="35" aria-label="Slider with reference labels" />
      <span
        class="text-muted-foreground mt-3 flex w-full items-center justify-between gap-1 text-xs font-medium tracking-tight sm:text-sm"
        aria-hidden="true"
      >
        <span>5 GB</span>
        <span>20 GB</span>
        <span>35 GB</span>
      </span>
    </div>
  </div>
</template>
