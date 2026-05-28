<script setup>
import { ref } from "vue";
import {
  Stepper,
  StepperDescription,
  StepperIndicator,
  StepperItem,
  StepperSeparator,
  StepperTitle,
} from "@/components/ui/stepper";

const step = ref(2);
const steps = [
  { step: 1, title: "Address", description: "Where to ship" },
  { step: 2, title: "Shipping", description: "Carrier and speed" },
  { step: 3, title: "Payment", description: "Card or bank" },
];
</script>

<template>
  <Stepper v-model="step" class="w-full max-w-2xl">
    <StepperItem v-for="(s, i) in steps" :key="s.step" :step="s.step" class="flex items-start gap-3">
      <StepperIndicator />
      <div class="flex flex-col">
        <StepperTitle>{{ s.title }}</StepperTitle>
        <StepperDescription>{{ s.description }}</StepperDescription>
      </div>
      <StepperSeparator v-if="i < steps.length - 1" />
    </StepperItem>
  </Stepper>
</template>
