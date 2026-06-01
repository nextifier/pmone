<script setup>
import { ref } from "vue";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";

const value = ref([25]);
</script>

<template>
  <div class="w-full max-w-sm space-y-3">
    <div class="flex items-center justify-between gap-2">
      <Label class="leading-6">Volume</Label>
      <span class="text-sm font-medium tabular-nums">{{ value[0] }}</span>
    </div>
    <div class="flex items-center gap-2">
      <Icon
        name="hugeicons:volume-mute-02"
        class="text-muted-foreground size-4 shrink-0"
        aria-hidden="true"
      />
      <Slider v-model="value" aria-label="Volume slider" />
      <Icon
        name="hugeicons:volume-high"
        class="text-muted-foreground size-4 shrink-0"
        aria-hidden="true"
      />
    </div>
  </div>
</template>
