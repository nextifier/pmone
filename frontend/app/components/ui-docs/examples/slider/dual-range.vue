<script setup>
import { ref } from "vue";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";

const value = ref([25, 75]);
</script>

<template>
  <div class="w-full max-w-sm space-y-4">
    <Label>Dual range slider</Label>
    <Slider v-model="value" :step="10" aria-label="Dual range slider" />
  </div>
</template>
