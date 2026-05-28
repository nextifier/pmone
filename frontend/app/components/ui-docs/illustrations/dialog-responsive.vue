<template>
  <div
    class="relative flex w-full max-w-36 flex-col overflow-hidden rounded-2xl border text-card-foreground shadow-md/5 not-dark:bg-clip-padding before:pointer-events-none before:absolute before:inset-0 before:rounded-[calc(var(--radius-2xl)-1px)] before:shadow-[0_-1px_--theme(--color-white/6%),0_1px_--theme(--color-black/6%)] bg-linear-to-b from-[color-mix(in_srgb,var(--card)_96%,var(--color-white))] to-[color-mix(in_srgb,var(--card)_99%,var(--color-black))] dark:to-[color-mix(in_srgb,var(--card)_98%,var(--color-white))] [--radius-2xl:14px]"
  >
    <div class="flex flex-1 flex-col gap-3 p-4 pt-2">
      <div class="bg-muted-foreground/40 mx-auto h-1 w-8 rounded-full" />
      <div class="bg-muted-foreground/40 h-1.5 w-3/5 rounded-full" />
      <div class="bg-muted-foreground/8 h-3.5 rounded-sm" />
      <div class="bg-muted-foreground/8 h-3.5 rounded-sm" />
      <div class="h-4 w-full rounded-sm bg-linear-to-b from-(--btn-from) to-(--btn-to)" />
    </div>
  </div>
</template>
