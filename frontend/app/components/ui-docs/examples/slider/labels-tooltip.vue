<script setup>
import { ref } from "vue";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";

const value = ref([50]);
</script>

<template>
  <div class="w-full max-w-sm space-y-4">
    <Label>Slider with labels and tooltip</Label>
    <div>
      <span
        class="text-muted-foreground mb-3 flex w-full items-center justify-between gap-2 text-xs font-medium tracking-tight sm:text-sm"
        aria-hidden="true"
      >
        <span>Low</span>
        <span>High</span>
      </span>
      <Slider
        v-model="value"
        :step="10"
        :show-tooltip="true"
        aria-label="Slider with labels and tooltip"
      />
    </div>
  </div>
</template>
