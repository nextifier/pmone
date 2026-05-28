<template>
  <div class="flex w-full max-w-50 flex-col gap-4">
    <div class="bg-muted-foreground/12 flex rounded-lg p-0.5">
      <div
        class="rounded-[calc(var(--radius-lg)-1px)] bg-linear-to-b from-card to-card/90 p-3 shadow-xs/5 dark:from-background/90 dark:to-background"
      >
        <div class="bg-primary h-1.5 w-6 rounded-full" />
      </div>
      <div class="rounded-[calc(var(--radius-lg)-1px)] p-3">
        <div class="bg-muted-foreground/20 h-1.5 w-6 rounded-full" />
      </div>
      <div class="rounded-[calc(var(--radius-lg)-1px)] p-3">
        <div class="bg-muted-foreground/20 h-1.5 w-6 rounded-full" />
      </div>
    </div>
    <div class="flex flex-col gap-2">
      <div class="bg-muted-foreground/40 h-1.5 w-[70%] rounded-full" />
      <div class="bg-muted-foreground/20 h-1.5 rounded-full" />
    </div>
  </div>
</template>
