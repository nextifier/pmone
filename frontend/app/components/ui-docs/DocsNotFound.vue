<template>
  <div class="my-6 min-w-0 flex-1 sm:p-10">
    <div class="mx-auto max-w-3xl text-center">
      <h1 class="text-primary text-3xl font-semibold tracking-tighter">Component not found</h1>
      <p class="text-muted-foreground mt-3 tracking-tight">
        This component is not in the registry yet.
      </p>
      <Button to="/ui" variant="outline" class="mt-6 tracking-tight">Back to library</Button>
    </div>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
</script>
