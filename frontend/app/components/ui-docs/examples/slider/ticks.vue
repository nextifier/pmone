<script setup>
import { cn } from "@/lib/utils";
import { ref } from "vue";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";

const value = ref([5]);
const max = 12;
const skipInterval = 2;
const ticks = [...Array(max + 1)].map((_, i) => i);
</script>

<template>
  <div class="w-full max-w-sm space-y-4">
    <Label>Slider with ticks</Label>
    <div>
      <Slider v-model="value" :max="max" aria-label="Slider with ticks" />
      <span
        class="text-muted-foreground mt-3 flex w-full items-center justify-between gap-1 px-2.5 text-xs font-medium tracking-tight"
        aria-hidden="true"
      >
        <span
          v-for="(_, i) in ticks"
          :key="i"
          class="flex w-0 flex-col items-center justify-center gap-2"
        >
          <span :class="cn('bg-muted-foreground/70 h-1 w-px', i % skipInterval !== 0 && 'h-0.5')" />
          <span :class="cn(i % skipInterval !== 0 && 'opacity-0')">{{ i }}</span>
        </span>
      </span>
    </div>
  </div>
</template>
