<script setup>
import { ref } from "vue";
import { Button } from "@/components/ui/button";

const loading = ref(false);

function submit() {
  loading.value = true;
  setTimeout(() => (loading.value = false), 1500);
}
</script>

<template>
  <Button :disabled="loading" @click="submit">
    <Icon v-if="loading" name="hugeicons:loading-03" class="animate-spin" />
    {{ loading ? "Saving" : "Save changes" }}
  </Button>
</template>
