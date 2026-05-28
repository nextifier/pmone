<script setup>
import { ref } from "vue";
import { Switch } from "@/components/ui/switch";

const on = ref(true);
</script>

<template>
  <Switch v-model="on" />
</template>
