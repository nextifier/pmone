<template>
  <div class="space-y-3">
    <div class="flex items-center gap-2">
      <Icon name="hugeicons:view" class="text-muted-foreground size-4" />
      <p class="text-muted-foreground text-sm tracking-tight">Live preview - brand page</p>
    </div>

    <!-- Preview frame mimics the public brand detail page -->
    <div class="bg-background overflow-hidden rounded-xl border p-5 sm:p-6">
      <BrandPreviewPage :preview="preview" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  preview: { type: Object, default: () => ({}) },
});
</script>
