<script setup>
import { onMounted, ref } from "vue";
import { CircularProgress } from "@/components/ui/circular-progress";

const value = ref(0);

onMounted(() => {
  let i = 0;
  const t = setInterval(() => {
    i = (i + 10) % 110;
    value.value = i;
  }, 600);
  return () => clearInterval(t);
});
</script>

<template>
  <CircularProgress :value="value" :size="72" :stroke-width="6" />
</template>
