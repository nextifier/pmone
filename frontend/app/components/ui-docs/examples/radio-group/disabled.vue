<script setup>
import { ref } from "vue";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Label } from "@/components/ui/label";

const plan = ref("monthly");
</script>

<template>
  <RadioGroup v-model="plan" disabled class="space-y-2">
    <div class="flex items-center gap-2">
      <RadioGroupItem id="d-monthly" value="monthly" />
      <Label for="d-monthly">Monthly</Label>
    </div>
    <div class="flex items-center gap-2">
      <RadioGroupItem id="d-yearly" value="yearly" />
      <Label for="d-yearly">Yearly</Label>
    </div>
    <div class="flex items-center gap-2">
      <RadioGroupItem id="d-lifetime" value="lifetime" />
      <Label for="d-lifetime">Lifetime</Label>
    </div>
  </RadioGroup>
</template>
