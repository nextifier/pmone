<script setup>
import { ref } from "vue";
import { Checkbox } from "@/components/ui/checkbox";

const checked = ref(true);
</script>

<template>
  <Checkbox v-model="checked" />
</template>
