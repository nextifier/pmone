<script setup>
import { ref } from "vue";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";

const value = ref([25]);
</script>

<template>
  <div class="w-full max-w-sm space-y-4">
    <Label>Simple slider</Label>
    <Slider v-model="value" aria-label="Simple slider" />
  </div>
</template>
