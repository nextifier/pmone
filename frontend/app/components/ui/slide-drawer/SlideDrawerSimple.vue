<script setup lang="ts">
const open = defineModel<boolean>('open', { default: false })

function closeDrawer() {
  open.value = false
}
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50">
    <!-- Overlay -->
    <div
      class="absolute inset-0 bg-black/60"
      @click="closeDrawer"
    />

    <!-- Drawer -->
    <div class="absolute inset-x-0 bottom-0 rounded-t-2xl bg-background p-4">
      <div class="mb-4 flex justify-center">
        <button
          type="button"
          class="h-1.5 w-12 rounded-full bg-muted-foreground/30"
          @click="closeDrawer"
        />
      </div>
      <slot />
    </div>
  </div>
</template>
