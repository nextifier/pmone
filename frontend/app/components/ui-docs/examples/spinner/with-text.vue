<script setup>
import { Spinner } from "@/components/ui/spinner";
</script>

<template>
  <div class="text-muted-foreground flex items-center gap-2 text-sm tracking-tight">
    <Spinner class="size-4" />
    <span>Loading…</span>
  </div>
</template>
