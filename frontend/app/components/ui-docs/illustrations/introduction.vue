<template>
  <div
    class="relative flex w-full max-w-36 flex-col rounded-2xl border text-card-foreground shadow-md/5 not-dark:bg-clip-padding before:pointer-events-none before:absolute before:inset-0 before:rounded-[calc(var(--radius-2xl)-1px)] before:shadow-[0_-1px_--theme(--color-white/6%),0_1px_--theme(--color-black/6%)] bg-linear-to-b from-[color-mix(in_srgb,var(--card)_96%,var(--color-white))] to-[color-mix(in_srgb,var(--card)_99%,var(--color-black))] dark:to-[color-mix(in_srgb,var(--card)_98%,var(--color-white))] [--radius-2xl:14px]"
  >
    <div class="flex flex-1 flex-col gap-2.5 p-4">
      <div class="bg-muted-foreground/40 h-1.5 w-2/3 rounded-full" />
      <div class="bg-muted-foreground/20 h-1.5 rounded-full" />
      <div class="bg-muted-foreground/20 h-1.5 w-5/6 rounded-full" />
      <div class="bg-muted-foreground/20 h-1.5 rounded-full" />
      <div class="bg-muted-foreground/20 h-1.5 w-3/4 rounded-full" />
    </div>
  </div>
</template>
