<script setup>
import { ScrollSpy } from "@/components/ui/scroll-spy";
</script>

<template>
  <div class="flex w-full gap-6">
    <article id="demo-article" class="flex-1 space-y-6">
      <h2>Introduction</h2>
      <p class="text-muted-foreground text-sm tracking-tight">Some intro copy here.</p>
      <h2>Installation</h2>
      <p class="text-muted-foreground text-sm tracking-tight">Steps to install.</p>
      <h2>Usage</h2>
      <p class="text-muted-foreground text-sm tracking-tight">How to use the component.</p>
    </article>
    <aside class="w-48 shrink-0">
      <ScrollSpy content-selector="#demo-article" />
    </aside>
  </div>
</template>
