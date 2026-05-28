<script setup>
import { ref } from "vue";
import { TipTapEditor } from "@/components/ui/tip-tap-editor";

const content = ref("<p>Hello from <strong>TipTap</strong>.</p>");
</script>

<template>
  <TipTapEditor v-model="content" class="w-full max-w-2xl" />
</template>
