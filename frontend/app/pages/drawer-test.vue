<script setup lang="ts">
import SlideDrawer from '../components/ui/slide-drawer/SlideDrawer.vue'

definePageMeta({
  ssr: false,
})

const isOpen = ref(false)

function toggle() {
  console.log('toggle called, isOpen was:', isOpen.value)
  isOpen.value = !isOpen.value
  console.log('isOpen is now:', isOpen.value)
}
</script>

<template>
  <main class="min-h-screen bg-background">
    <div class="container py-8">
      <h1 class="text-2xl font-bold mb-4">Drawer Test Page</h1>

      <p class="mb-4">isOpen: <strong>{{ isOpen }}</strong></p>

      <button
        class="px-4 py-2 bg-primary text-primary-foreground rounded-md"
        @click="toggle"
      >
        Toggle (isOpen: {{ isOpen }})
      </button>

      <SlideDrawer v-model:open="isOpen">
        <div class="p-4">
          <h2 class="text-lg font-semibold mb-2">Test Drawer</h2>
          <p class="text-muted-foreground mb-4">This is a test drawer using scroll-snap</p>
          <p>Swipe down to close. The drawer uses native scroll behavior!</p>

          <div class="mt-4 space-y-2">
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
            <p>Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
            <p>Ut enim ad minim veniam, quis nostrud exercitation.</p>
          </div>

          <button
            class="mt-4 px-4 py-2 bg-secondary text-secondary-foreground rounded-md"
            @click="isOpen = false"
          >
            Close Drawer
          </button>
        </div>
      </SlideDrawer>
    </div>
  </main>
</template>
