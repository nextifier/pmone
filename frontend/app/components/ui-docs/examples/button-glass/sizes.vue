<script setup>
import { ButtonGlass } from "@/components/ui/button-glass";
</script>

<template>
  <div class="bg-linear-to-br from-emerald-500 to-cyan-600 flex flex-wrap items-center gap-3 rounded-2xl p-8">
    <ButtonGlass size="sm">Small</ButtonGlass>
    <ButtonGlass size="md">Medium</ButtonGlass>
    <ButtonGlass size="lg">Large</ButtonGlass>
    <ButtonGlass size="xl">Extra large</ButtonGlass>
  </div>
</template>
