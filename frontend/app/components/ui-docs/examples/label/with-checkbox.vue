<script setup>
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
</script>

<template>
  <div class="flex items-center gap-2">
    <Checkbox id="terms" />
    <Label for="terms">Accept terms and conditions</Label>
  </div>
</template>
