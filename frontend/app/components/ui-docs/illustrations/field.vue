<template>
  <div class="flex w-full max-w-72 flex-col gap-2">
    <div class="bg-muted-foreground/88 h-1.5 w-12 rounded-full" />
    <div
      class="relative flex w-full flex-col rounded-2xl border text-card-foreground shadow-md/5 not-dark:bg-clip-padding before:pointer-events-none before:absolute before:inset-0 before:rounded-[calc(var(--radius-2xl)-1px)] before:shadow-[0_-1px_--theme(--color-white/6%),0_1px_--theme(--color-black/6%)] bg-card/99 dark:bg-card [--radius-2xl:14px]"
    >
      <div class="flex-1 px-4 py-3">
        <div class="bg-muted-foreground/40 h-1.5 w-3/5 rounded-full" />
      </div>
    </div>
    <div class="bg-muted-foreground/20 h-1 w-2/3 rounded-full" />
  </div>
</template>
