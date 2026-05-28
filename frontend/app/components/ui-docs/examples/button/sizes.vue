<script setup>
import { Button } from "@/components/ui/button";
</script>

<template>
  <Button size="sm">Small</Button>
  <Button size="default">Default</Button>
  <Button size="lg">Large</Button>
  <Button size="iconSm" aria-label="Small icon">
    <Icon name="hugeicons:add-01" />
  </Button>
  <Button size="icon" aria-label="Icon">
    <Icon name="hugeicons:add-01" />
  </Button>
</template>
