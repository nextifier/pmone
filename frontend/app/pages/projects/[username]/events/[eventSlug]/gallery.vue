<template>
  <div class="flex flex-col items-center justify-center px-4 py-16">
    <div class="flex flex-col items-center justify-center gap-y-4 text-center">
      <div
        class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
      >
        <div class="translate-y-1.5 -rotate-6">
          <Icon name="hugeicons:image-01" />
        </div>
        <div>
          <Icon name="hugeicons:layers-01" />
        </div>
        <div class="translate-y-1.5 rotate-6">
          <Icon name="hugeicons:folder-add" />
        </div>
      </div>

      <div class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight">Gallery</h1>
        <p class="text-muted-foreground max-w-md text-sm">
          Event photos and media.
        </p>
      </div>

      <div class="bg-muted text-muted-foreground mt-2 rounded-full px-3 py-1 text-xs font-medium">
        Coming Soon
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({ event: Object, project: Object });
usePageMeta(null, {
  title: 'Gallery - Coming Soon',
})
</script>
