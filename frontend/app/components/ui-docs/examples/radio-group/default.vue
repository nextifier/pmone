<script setup>
import { ref } from "vue";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Label } from "@/components/ui/label";

const plan = ref("monthly");
</script>

<template>
  <RadioGroup v-model="plan" class="space-y-2">
    <div class="flex items-center gap-2">
      <RadioGroupItem id="monthly" value="monthly" />
      <Label for="monthly">Monthly</Label>
    </div>
    <div class="flex items-center gap-2">
      <RadioGroupItem id="yearly" value="yearly" />
      <Label for="yearly">Yearly</Label>
    </div>
    <div class="flex items-center gap-2">
      <RadioGroupItem id="lifetime" value="lifetime" />
      <Label for="lifetime">Lifetime</Label>
    </div>
  </RadioGroup>
</template>
