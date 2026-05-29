<script setup>
import { ref } from "vue";
import { IconPicker } from "@/components/ui/icon-picker";

const icon = ref();
</script>

<template>
  <div class="flex items-center gap-3">
    <IconPicker v-model="icon" prefix="lucide" placeholder="Pick a Lucide icon" />
    <Icon v-if="icon" :name="icon" class="size-6" />
  </div>
</template>
