<script setup lang="ts">
import SlideDrawer from '../components/ui/slide-drawer/SlideDrawer.vue'
import SlideDrawerHeader from '../components/ui/slide-drawer/SlideDrawerHeader.vue'
import SlideDrawerTitle from '../components/ui/slide-drawer/SlideDrawerTitle.vue'
import SlideDrawerDescription from '../components/ui/slide-drawer/SlideDrawerDescription.vue'
import SlideDrawerBody from '../components/ui/slide-drawer/SlideDrawerBody.vue'

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
      <SlideDrawerHeader>
        <SlideDrawerTitle>Test Drawer</SlideDrawerTitle>
        <SlideDrawerDescription>This is a test drawer</SlideDrawerDescription>
      </SlideDrawerHeader>
      <SlideDrawerBody>
        <p>Drawer content here! Swipe down to close.</p>
      </SlideDrawerBody>
    </SlideDrawer>
  </div>
</template>
