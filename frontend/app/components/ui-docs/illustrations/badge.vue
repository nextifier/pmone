<template>
  <div
    class="relative flex w-full max-w-24 flex-col rounded-2xl border text-card-foreground shadow-md/5 not-dark:bg-clip-padding before:pointer-events-none before:absolute before:inset-0 before:rounded-[calc(var(--radius-2xl)-1px)] before:shadow-[0_-1px_--theme(--color-white/6%),0_1px_--theme(--color-black/6%)] bg-linear-to-b from-[color-mix(in_srgb,var(--card)_96%,var(--color-white))] to-[color-mix(in_srgb,var(--card)_99%,var(--color-black))] dark:to-[color-mix(in_srgb,var(--card)_98%,var(--color-white))] [--radius-2xl:9999px]"
  >
    <div class="flex flex-1 items-center gap-2 px-2.5 py-2">
      <div class="bg-muted-foreground/88 size-2 rounded-full" />
      <div class="bg-muted-foreground/40 h-1.5 flex-1 rounded-full" />
    </div>
  </div>
</template>
