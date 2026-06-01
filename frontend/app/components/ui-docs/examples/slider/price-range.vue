<script setup>
import { ref } from "vue";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";

const minPrice = 5;
const maxPrice = 1240;
const value = ref([minPrice, maxPrice]);

const formatPrice = (price) =>
  price === maxPrice ? `$${price.toLocaleString()}+` : `$${price.toLocaleString()}`;
</script>

<template>
  <div class="w-full max-w-sm space-y-3">
    <Label class="tabular-nums">
      From {{ formatPrice(value[0] || minPrice) }} to {{ formatPrice(value[1] || maxPrice) }}
    </Label>
    <div class="flex items-center gap-4">
      <Slider v-model="value" :min="minPrice" :max="maxPrice" aria-label="Price range slider" />
      <Button variant="outline">Go</Button>
    </div>
  </div>
</template>
