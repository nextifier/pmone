<script setup>
import { ref } from "vue";
import { PinInput, PinInputGroup, PinInputSlot } from "@/components/ui/pin-input";

const pin = ref([]);
</script>

<template>
  <PinInput v-model="pin" mask>
    <PinInputGroup>
      <PinInputSlot v-for="i in 4" :key="i" :index="i - 1" />
    </PinInputGroup>
  </PinInput>
</template>
