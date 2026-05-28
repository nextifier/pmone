<script setup>
import { onMounted, ref } from "vue";
import { Progress } from "@/components/ui/progress";

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
  <Progress :model-value="value" class="w-full max-w-sm" />
</template>
