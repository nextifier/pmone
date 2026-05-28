<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";

const rows = [
  ["strong", "weak", "weak", "weak"],
  ["weak", "weak", "weak", "weak"],
  ["weak", "weak", "weak", "weak"],
];
</script>

<template>
  <IllustrationFrame max-width="72">
    <div class="divide-border flex-1 divide-y">
      <div v-for="(row, i) in rows" :key="i" class="flex items-center gap-2 p-3">
        <div class="bg-muted-foreground/40 size-2.5 rounded-xs" />
        <div
          v-for="(weight, j) in row"
          :key="j"
          :class="[
            'h-1.5 flex-1 rounded-full',
            weight === 'strong' ? 'bg-muted-foreground/40' : 'bg-muted-foreground/20',
          ]"
        />
      </div>
    </div>
  </IllustrationFrame>
</template>
