<script setup lang="ts">
interface Props {
  showHandle?: boolean
  closeOnOverlayClick?: boolean
  snapThreshold?: number
}

const props = withDefaults(defineProps<Props>(), {
  showHandle: true,
  closeOnOverlayClick: true,
  snapThreshold: 0.25, // 25% of drawer height
})

const open = defineModel<boolean>('open', { default: false })

// Manual scroll lock implementation (client-side only)
const scrollbarWidth = ref(0)
const scrollPosition = ref(0)

function lockBodyScroll() {
  if (import.meta.server) return

  // Store current scroll position
  scrollPosition.value = window.scrollY

  // Calculate scrollbar width before hiding it
  scrollbarWidth.value = window.innerWidth - document.documentElement.clientWidth

  // Apply styles to both html and body to fully prevent scroll
  document.documentElement.style.overflow = 'hidden'
  document.body.style.overflow = 'hidden'
  document.body.style.paddingRight = `${scrollbarWidth.value}px`

  // Fix body position to prevent iOS bounce scroll
  document.body.style.position = 'fixed'
  document.body.style.top = `-${scrollPosition.value}px`
  document.body.style.left = '0'
  document.body.style.right = '0'
}

function unlockBodyScroll() {
  if (import.meta.server) return

  // Remove all scroll lock styles
  document.documentElement.style.overflow = ''
  document.body.style.overflow = ''
  document.body.style.paddingRight = ''
  document.body.style.position = ''
  document.body.style.top = ''
  document.body.style.left = ''
  document.body.style.right = ''

  // Restore scroll position
  window.scrollTo(0, scrollPosition.value)
}

// Watch open state to lock/unlock body scroll (only on client)
watch(open, (isOpen) => {
  if (import.meta.server) return

  if (isOpen) {
    lockBodyScroll()
  } else {
    unlockBodyScroll()
  }
})

// Lock on mount if already open
onMounted(() => {
  if (open.value) {
    lockBodyScroll()
  }
})

// Cleanup on unmount
onUnmounted(() => {
  unlockBodyScroll()
})

const drawerRef = ref<HTMLElement | null>(null)
const contentRef = ref<HTMLElement | null>(null)
const overlayRef = ref<HTMLElement | null>(null)

// Drag state
const isDragging = ref(false)
const dragStartY = ref(0)
const dragStartTime = ref(0)
const currentTranslateY = ref(0)
const drawerHeight = ref(0)

// Flag to skip leave animation when closing via swipe
const isClosingViaSwipe = ref(false)

// Minimum drag distance before starting actual drag (for input elements)
const MIN_DRAG_THRESHOLD = 10

// Velocity threshold for quick swipe
const VELOCITY_THRESHOLD = 0.5

function closeDrawer() {
  open.value = false
}

function handleOverlayClick() {
  if (props.closeOnOverlayClick) {
    closeDrawer()
  }
}

// Check if element or its parents are scrollable and not at top
function isContentScrolled(element: HTMLElement | null): boolean {
  if (!element || !contentRef.value) return false

  let current: HTMLElement | null = element

  while (current && current !== drawerRef.value) {
    if (current.scrollHeight > current.clientHeight && current.scrollTop > 0) {
      return true
    }
    current = current.parentElement
  }

  // Check the content wrapper itself
  if (contentRef.value.scrollTop > 0) {
    return true
  }

  return false
}

function handlePointerDown(event: PointerEvent) {
  // Don't start drag if clicking on buttons or links (they need click events)
  const target = event.target as HTMLElement
  if (target.closest('button, a, [role="button"]')) {
    return
  }

  // Don't start drag if content is scrolled
  if (isContentScrolled(target)) {
    return
  }

  isDragging.value = true
  dragStartY.value = event.clientY
  dragStartTime.value = Date.now()
  currentTranslateY.value = 0

  if (drawerRef.value) {
    drawerHeight.value = drawerRef.value.offsetHeight
    drawerRef.value.style.transition = 'none'
  }

  // Update overlay opacity in real-time
  if (overlayRef.value) {
    overlayRef.value.style.transition = 'none'
  }

  // Capture pointer for smooth tracking
  ;(event.target as HTMLElement).setPointerCapture?.(event.pointerId)
}

function handlePointerMove(event: PointerEvent) {
  if (!isDragging.value) return

  const deltaY = event.clientY - dragStartY.value

  // Only allow dragging down (positive deltaY)
  if (deltaY > 0) {
    currentTranslateY.value = deltaY

    // Apply transform with damping for resistance feel
    const dampedTranslate = deltaY

    if (drawerRef.value) {
      drawerRef.value.style.transform = `translateY(${dampedTranslate}px)`
    }

    // Fade overlay as drawer is dragged
    if (overlayRef.value && drawerHeight.value > 0) {
      const progress = Math.min(deltaY / drawerHeight.value, 1)
      overlayRef.value.style.opacity = String(1 - progress * 0.5)
    }
  }
}

function handlePointerUp(event: PointerEvent) {
  if (!isDragging.value) return

  isDragging.value = false

  // Release pointer capture
  ;(event.target as HTMLElement).releasePointerCapture?.(event.pointerId)

  // Calculate velocity
  const elapsed = Date.now() - dragStartTime.value
  const velocity = currentTranslateY.value / elapsed // pixels per ms

  if (drawerRef.value) {
    drawerRef.value.style.transition = 'transform 0.3s cubic-bezier(0.32, 0.72, 0, 1)'

    // Close if: velocity exceeds threshold OR dragged more than snapThreshold of height
    const shouldClose = velocity > VELOCITY_THRESHOLD ||
      currentTranslateY.value > drawerHeight.value * props.snapThreshold

    if (shouldClose) {
      // Mark as closing via swipe to skip Vue leave animation
      isClosingViaSwipe.value = true
      drawerRef.value.style.transform = 'translateY(100%)'

      if (overlayRef.value) {
        overlayRef.value.style.transition = 'opacity 0.3s cubic-bezier(0.32, 0.72, 0, 1)'
        overlayRef.value.style.opacity = '0'
      }

      setTimeout(() => {
        closeDrawer()
        // Reset after a brief delay to ensure DOM is updated
        nextTick(() => {
          isClosingViaSwipe.value = false
          resetStyles()
        })
      }, 300)
    }
    else {
      // Snap back
      drawerRef.value.style.transform = 'translateY(0)'

      if (overlayRef.value) {
        overlayRef.value.style.transition = 'opacity 0.3s cubic-bezier(0.32, 0.72, 0, 1)'
        overlayRef.value.style.opacity = '1'
      }

      setTimeout(resetStyles, 300)
    }
  }

  currentTranslateY.value = 0
}

function resetStyles() {
  if (drawerRef.value) {
    drawerRef.value.style.transform = ''
    drawerRef.value.style.transition = ''
  }
  if (overlayRef.value) {
    overlayRef.value.style.opacity = ''
    overlayRef.value.style.transition = ''
  }
}

// Handle escape key
function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && open.value) {
    closeDrawer()
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
})

// Reset styles when drawer opens
watch(open, (isOpen) => {
  if (isOpen) {
    nextTick(resetStyles)
  }
})
</script>

<template>
  <Transition :name="isClosingViaSwipe ? '' : 'slide-drawer'" :css="!isClosingViaSwipe">
    <div v-if="open" class="slide-drawer-wrapper">
      <!-- Overlay -->
      <div
        ref="overlayRef"
        class="slide-drawer-overlay"
        @click="handleOverlayClick"
      />

      <!-- Drawer -->
      <div
        ref="drawerRef"
        class="slide-drawer-container"
        @pointerdown="handlePointerDown"
        @pointermove="handlePointerMove"
        @pointerup="handlePointerUp"
        @pointercancel="handlePointerUp"
        @click.stop
      >
        <!-- Handle -->
        <div v-if="showHandle" class="slide-drawer-handle-wrapper">
          <div class="slide-drawer-handle" />
        </div>

        <!-- Content -->
        <div ref="contentRef" class="slide-drawer-content">
          <slot />
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.slide-drawer-wrapper {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
}

.slide-drawer-overlay {
  position: absolute;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
}

.slide-drawer-container {
  position: relative;
  max-height: 90dvh;
  overflow: hidden;
  border-radius: 1rem 1rem 0 0;
  background-color: white;
  box-shadow: 0 -10px 40px -10px rgba(0, 0, 0, 0.2);
  will-change: transform;
  touch-action: none;
  cursor: grab;
  user-select: none;
}

.slide-drawer-container:active {
  cursor: grabbing;
}

:root.dark .slide-drawer-container {
  background-color: hsl(240 10% 3.9%);
}

.slide-drawer-handle-wrapper {
  position: sticky;
  top: 0;
  z-index: 10;
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: center;
  padding: 0.75rem 0;
  background-color: inherit;
  border-radius: 1rem 1rem 0 0;
}

.slide-drawer-handle {
  width: 3rem;
  height: 0.25rem;
  border-radius: 9999px;
  background-color: rgba(0, 0, 0, 0.3);
  transition: background-color 0.2s, width 0.2s;
}

.slide-drawer-container:hover .slide-drawer-handle {
  background-color: rgba(0, 0, 0, 0.4);
}

:root.dark .slide-drawer-handle {
  background-color: rgba(255, 255, 255, 0.3);
}

:root.dark .slide-drawer-container:hover .slide-drawer-handle {
  background-color: rgba(255, 255, 255, 0.4);
}

.slide-drawer-content {
  overflow-y: auto;
  max-height: calc(90dvh - 2.5rem);
  overscroll-behavior: contain;
  padding-bottom: env(safe-area-inset-bottom, 0);
  touch-action: pan-y;
  cursor: auto;
  user-select: auto;
}

/* Transition animations */
.slide-drawer-enter-active {
  transition: opacity 0.3s cubic-bezier(0.32, 0.72, 0, 1);
}

.slide-drawer-leave-active {
  transition: opacity 0.2s cubic-bezier(0.32, 0.72, 0, 1);
}

.slide-drawer-enter-from,
.slide-drawer-leave-to {
  opacity: 0;
}

.slide-drawer-enter-active .slide-drawer-container {
  animation: slide-up 0.4s cubic-bezier(0.32, 0.72, 0, 1);
}

.slide-drawer-leave-active .slide-drawer-container {
  animation: slide-down 0.25s cubic-bezier(0.32, 0.72, 0, 1);
}

@keyframes slide-up {
  from {
    transform: translateY(100%);
  }
  to {
    transform: translateY(0);
  }
}

@keyframes slide-down {
  from {
    transform: translateY(0);
  }
  to {
    transform: translateY(100%);
  }
}
</style>
