<script setup>
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
</script>

<template>
  <div class="flex items-center gap-2">
    <Checkbox id="locked" disabled :model-value="true" />
    <Label for="locked" class="opacity-60">Two-factor authentication</Label>
  </div>
</template>
