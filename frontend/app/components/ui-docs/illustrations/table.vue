<template>
  <div
    class="relative flex w-full max-w-72 flex-col rounded-2xl border text-card-foreground shadow-md/5 not-dark:bg-clip-padding before:pointer-events-none before:absolute before:inset-0 before:rounded-[calc(var(--radius-2xl)-1px)] before:shadow-[0_-1px_--theme(--color-white/6%),0_1px_--theme(--color-black/6%)] bg-linear-to-b from-[color-mix(in_srgb,var(--card)_96%,var(--color-white))] to-[color-mix(in_srgb,var(--card)_99%,var(--color-black))] dark:to-[color-mix(in_srgb,var(--card)_98%,var(--color-white))]"
  >
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
  </div>
</template>

<script setup>
const rows = [
  ["strong", "weak", "weak", "weak"],
  ["weak", "weak", "weak", "weak"],
  ["weak", "weak", "weak", "weak"],
];
</script>
