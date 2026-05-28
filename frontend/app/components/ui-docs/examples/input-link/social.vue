<script setup>
import { ref } from "vue";
import { InputLink } from "@/components/ui/input-link";

const instagram = ref("");
const x = ref("");
const tiktok = ref("");
</script>

<template>
  <div class="flex w-full max-w-sm flex-col gap-3">
    <InputLink v-model="instagram" label="Instagram" />
    <InputLink v-model="x" label="X" />
    <InputLink v-model="tiktok" label="TikTok" />
  </div>
</template>
