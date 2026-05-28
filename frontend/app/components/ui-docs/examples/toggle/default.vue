<script setup>
import { ref } from "vue";
import { Toggle } from "@/components/ui/toggle";

const pressed = ref(false);
</script>

<template>
  <Toggle v-model="pressed" aria-label="Toggle italic">
    <Icon name="lucide:italic" />
  </Toggle>
</template>
