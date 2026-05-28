<script setup>
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from "@/components/ui/empty";
</script>

<template>
  <Empty class="border-border w-full max-w-md border">
    <EmptyHeader>
      <EmptyTitle>No results</EmptyTitle>
      <EmptyDescription>Try adjusting your filters.</EmptyDescription>
    </EmptyHeader>
  </Empty>
</template>
