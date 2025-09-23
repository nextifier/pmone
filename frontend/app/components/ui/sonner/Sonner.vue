<script lang="ts" setup>
import { Toaster as Sonner, type ToasterProps } from "vue-sonner";

const props = defineProps<ToasterProps>();
</script>

<template>
  <Sonner
    class="toaster group tracking-tight"
    v-bind="props"
    :style="{
      '--normal-bg': 'var(--foreground)',
      '--normal-text': 'var(--background)',
      '--normal-border': 'transparent',
    }"
  />
</template>
