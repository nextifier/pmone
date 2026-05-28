<script setup>
import { ref } from "vue";
import { Textarea } from "@/components/ui/textarea";

const value = ref("");
</script>

<template>
  <Textarea v-model="value" placeholder="Type your message here" class="w-full max-w-sm" />
</template>
