<script setup>
import { ref } from "vue";
import { InputLink } from "@/components/ui/input-link";

const url = ref("");
</script>

<template>
  <InputLink v-model="url" label="Website" class="w-full max-w-sm" />
</template>
