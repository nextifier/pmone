<template>
  <div class="flex flex-col gap-y-2">
    <div class="relative aspect-16/10 overflow-hidden rounded-xl bg-muted">
      <div
        class="absolute inset-x-0 bottom-0 flex h-20 w-full items-end justify-between px-3 pb-2.5"
      >
        <span class="skeleton h-3 w-20"></span>
        <span class="skeleton h-3 w-16"></span>
      </div>
    </div>

    <div class="mt-2 flex w-full flex-col items-start gap-y-2 px-1">
      <span class="skeleton h-4 w-full"></span>
      <span class="skeleton h-4 w-3/4"></span>

      <span class="skeleton mt-2 h-3 w-16"></span>
    </div>
  </div>
</template>
