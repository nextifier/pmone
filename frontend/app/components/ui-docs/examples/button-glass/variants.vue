<script setup>
import { ButtonGlass } from "@/components/ui/button-glass";
</script>

<template>
  <div class="bg-linear-to-br from-slate-700 to-slate-900 flex flex-wrap items-center gap-3 rounded-2xl p-8">
    <ButtonGlass variant="default">Default</ButtonGlass>
    <ButtonGlass variant="outline">Outline</ButtonGlass>
    <ButtonGlass variant="white">White</ButtonGlass>
    <ButtonGlass variant="ghost">Ghost</ButtonGlass>
  </div>
</template>
