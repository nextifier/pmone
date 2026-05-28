<script setup>
import { ref } from "vue";
import { IconPicker } from "@/components/ui/icon-picker";

const icon = ref("hugeicons:rocket");
</script>

<template>
  <div class="flex items-center gap-3">
    <IconPicker v-model="icon" />
    <Icon v-if="icon" :name="icon" class="size-6" />
  </div>
</template>
