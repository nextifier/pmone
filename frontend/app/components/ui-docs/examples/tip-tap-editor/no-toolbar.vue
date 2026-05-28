<script setup>
import { ref } from "vue";
import { TipTapEditor } from "@/components/ui/tip-tap-editor";

const bio = ref("");
</script>

<template>
  <TipTapEditor v-model="bio" minimal placeholder="Write a short bio" class="w-full max-w-md" />
</template>
