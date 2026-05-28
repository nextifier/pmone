<script setup>
import { ref } from "vue";
import { Input } from "@/components/ui/input";

const value = ref("");
</script>

<template>
  <Input v-model="value" placeholder="Type something" class="max-w-xs" />
</template>
