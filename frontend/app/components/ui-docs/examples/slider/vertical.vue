<script setup>
import { ref } from "vue";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";

const value = ref([5]);
</script>

<template>
  <div class="space-y-4">
    <Label>Vertical slider</Label>
    <div class="flex h-40 justify-center">
      <Slider v-model="value" :max="10" orientation="vertical" aria-label="Vertical slider" />
    </div>
  </div>
</template>
