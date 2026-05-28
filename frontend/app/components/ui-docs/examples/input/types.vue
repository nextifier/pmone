<script setup>
import { Input } from "@/components/ui/input";
</script>

<template>
  <div class="flex w-full max-w-xs flex-col gap-3">
    <Input type="text" placeholder="Text" />
    <Input type="email" placeholder="Email" />
    <Input type="number" placeholder="Number" />
    <Input type="date" />
    <Input type="file" />
  </div>
</template>
