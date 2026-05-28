<script setup>
import { ref } from "vue";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";

const formats = ref(["bold"]);
</script>

<template>
  <ToggleGroup v-model="formats" type="multiple">
    <ToggleGroupItem value="bold" aria-label="Bold">
      <Icon name="lucide:bold" />
    </ToggleGroupItem>
    <ToggleGroupItem value="italic" aria-label="Italic">
      <Icon name="lucide:italic" />
    </ToggleGroupItem>
    <ToggleGroupItem value="underline" aria-label="Underline">
      <Icon name="lucide:underline" />
    </ToggleGroupItem>
  </ToggleGroup>
</template>
