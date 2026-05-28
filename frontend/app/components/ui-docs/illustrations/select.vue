<template>
  <div
    class="relative flex w-full max-w-72 flex-col rounded-2xl border text-card-foreground shadow-md/5 not-dark:bg-clip-padding before:pointer-events-none before:absolute before:inset-0 before:rounded-[calc(var(--radius-2xl)-1px)] before:shadow-[0_-1px_--theme(--color-white/6%),0_1px_--theme(--color-black/6%)] bg-card/99 dark:bg-card [--radius-2xl:14px]"
  >
    <div class="flex flex-1 items-center justify-between gap-2 py-2.5 ps-4 pe-2.5">
      <div class="bg-muted-foreground/40 h-1.5 w-3/5 rounded-full" />
      <Icon name="lucide:chevron-down" class="text-muted-foreground/88 size-4" />
    </div>
  </div>
</template>
