<script setup>
import { Skeleton } from "@/components/ui/skeleton";
</script>

<template>
  <div class="flex w-full max-w-sm items-center gap-3">
    <Skeleton class="size-12 rounded-full" />
    <div class="flex-1 space-y-2">
      <Skeleton class="h-4 w-2/3" />
      <Skeleton class="h-3 w-full" />
    </div>
  </div>
</template>
