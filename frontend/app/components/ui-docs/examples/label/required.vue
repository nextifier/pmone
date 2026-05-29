<script setup>
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
</script>

<template>
  <div class="grid w-full max-w-sm items-center gap-1.5">
    <Label for="full-name">
      Full name
      <span class="text-destructive">*</span>
    </Label>
    <Input id="full-name" placeholder="Olivia Martin" required />
  </div>
</template>
