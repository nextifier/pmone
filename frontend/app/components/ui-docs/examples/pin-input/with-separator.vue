<script setup>
import { ref } from "vue";
import {
  PinInput,
  PinInputGroup,
  PinInputSeparator,
  PinInputSlot,
} from "@/components/ui/pin-input";

const pin = ref([]);
</script>

<template>
  <PinInput v-model="pin">
    <PinInputGroup>
      <PinInputSlot v-for="i in 3" :key="i" :index="i - 1" />
    </PinInputGroup>
    <PinInputSeparator />
    <PinInputGroup>
      <PinInputSlot v-for="i in 3" :key="i" :index="i + 2" />
    </PinInputGroup>
  </PinInput>
</template>
