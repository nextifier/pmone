<script setup>
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";
</script>

<template>
  <div class="flex items-center gap-2 opacity-60">
    <Switch id="locked" disabled :model-value="true" />
    <Label for="locked">Locked</Label>
  </div>
</template>
