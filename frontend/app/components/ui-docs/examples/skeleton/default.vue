<script setup>
import { Skeleton } from "@/components/ui/skeleton";
</script>

<template>
  <div class="flex w-full max-w-sm flex-col gap-2">
    <Skeleton class="h-5 w-2/3" />
    <Skeleton class="h-4 w-full" />
    <Skeleton class="h-4 w-4/5" />
  </div>
</template>
