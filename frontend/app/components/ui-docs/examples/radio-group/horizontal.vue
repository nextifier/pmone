<script setup>
import { ref } from "vue";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Label } from "@/components/ui/label";

const size = ref("md");
</script>

<template>
  <RadioGroup v-model="size" class="flex gap-4">
    <div class="flex items-center gap-2">
      <RadioGroupItem id="sm" value="sm" />
      <Label for="sm">Small</Label>
    </div>
    <div class="flex items-center gap-2">
      <RadioGroupItem id="md" value="md" />
      <Label for="md">Medium</Label>
    </div>
    <div class="flex items-center gap-2">
      <RadioGroupItem id="lg" value="lg" />
      <Label for="lg">Large</Label>
    </div>
  </RadioGroup>
</template>
