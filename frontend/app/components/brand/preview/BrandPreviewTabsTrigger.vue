<template>
  <TabsList
    class="bg-muted text-body relative isolate inline-flex items-center justify-center rounded-full border p-0.5 shadow-lg"
  >
    <TabsIndicator
      class="absolute inset-y-0.5 left-0 z-0 w-(--reka-tabs-indicator-size) translate-x-(--reka-tabs-indicator-position) rounded-full transition-all duration-300 ease-in-out"
    >
      <div class="bg-primary size-full rounded-full" />
    </TabsIndicator>
    <TabsTrigger
      value="edit"
      class="data-[state=active]:text-primary-foreground relative z-10 inline-flex h-8 items-center justify-center rounded-full px-4 text-sm font-medium tracking-tight whitespace-nowrap transition-all"
    >
      Edit
    </TabsTrigger>
    <TabsTrigger
      value="preview"
      class="data-[state=active]:text-primary-foreground relative z-10 inline-flex h-8 items-center justify-center rounded-full px-4 text-sm font-medium tracking-tight whitespace-nowrap transition-all"
    >
      Preview
    </TabsTrigger>
  </TabsList>
</template>

<script setup>
import { TabsIndicator, TabsList, TabsTrigger } from "reka-ui";
</script>
