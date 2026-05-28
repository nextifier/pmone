<script setup>
import { ref } from "vue";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";

const align = ref("center");
</script>

<template>
  <ToggleGroup v-model="align" type="single">
    <ToggleGroupItem value="left" aria-label="Align left">
      <Icon name="lucide:align-left" />
    </ToggleGroupItem>
    <ToggleGroupItem value="center" aria-label="Align center">
      <Icon name="lucide:align-center" />
    </ToggleGroupItem>
    <ToggleGroupItem value="right" aria-label="Align right">
      <Icon name="lucide:align-right" />
    </ToggleGroupItem>
  </ToggleGroup>
</template>
