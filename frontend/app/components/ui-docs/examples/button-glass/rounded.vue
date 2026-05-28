<script setup>
import { ButtonGlass } from "@/components/ui/button-glass";
</script>

<template>
  <div class="bg-linear-to-br from-rose-500 to-orange-600 flex flex-wrap items-center gap-3 rounded-2xl p-8">
    <ButtonGlass rounded="md">Medium</ButtonGlass>
    <ButtonGlass rounded="lg">Large</ButtonGlass>
    <ButtonGlass rounded="2xl">2XL</ButtonGlass>
    <ButtonGlass rounded="full">Full</ButtonGlass>
  </div>
</template>
