<script setup lang="ts">
interface Props {
  showHandle?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  showHandle: true,
})

const open = defineModel<boolean>('open', { default: false })

const drawerRef = ref<HTMLElement | null>(null)
const scrollerRef = ref<HTMLElement | null>(null)
const topAnchorRef = ref<HTMLElement | null>(null)

let observer: IntersectionObserver | null = null

// Handle popover toggle event
function handleToggle(event: ToggleEvent) {
  if (event.newState === 'closed') {
    open.value = false
  } else if (event.newState === 'open') {
    open.value = true
    // Scroll to bottom to show drawer
    nextTick(() => {
      if (scrollerRef.value) {
        scrollerRef.value.scrollTop = scrollerRef.value.scrollHeight
      }
    })

    // Fallback for browsers without scrollsnapchange
    if (!('onscrollsnapchange' in window) && topAnchorRef.value) {
      if (!observer) {
        observer = new IntersectionObserver(
          (entries) => {
            entries.forEach((entry) => {
              if (entry.isIntersecting && entry.intersectionRatio > 0.5) {
                drawerRef.value?.hidePopover()
              }
            })
          },
          { threshold: [0.5] },
        )
      }
      observer.observe(topAnchorRef.value)
    }
  }
}

// Handle scroll snap change
function handleScrollSnapChange() {
  if (scrollerRef.value && scrollerRef.value.scrollTop === 0) {
    drawerRef.value?.hidePopover()
  }
}

// Watch open state to show/hide popover
watch(open, (isOpen) => {
  if (import.meta.server) return

  nextTick(() => {
    if (isOpen) {
      drawerRef.value?.showPopover()
    } else {
      drawerRef.value?.hidePopover()
    }
  })
})

// Handle visual viewport resize (for keyboard)
function handleViewportResize() {
  if (window.visualViewport) {
    document.documentElement.style.setProperty(
      '--viewport-offset',
      String(window.visualViewport.offsetTop),
    )
  }
}

onMounted(() => {
  window.visualViewport?.addEventListener('resize', handleViewportResize)
})

onUnmounted(() => {
  window.visualViewport?.removeEventListener('resize', handleViewportResize)
  observer?.disconnect()
})
</script>

<template>
  <div
    ref="drawerRef"
    popover="manual"
    class="slide-drawer"
    @toggle="handleToggle"
  >
    <div
      ref="scrollerRef"
      class="slide-drawer__scroller"
      @scrollsnapchange="handleScrollSnapChange"
    >
      <!-- Snap anchors -->
      <div class="slide-drawer__anchors">
        <div ref="topAnchorRef" class="slide-drawer__anchor slide-drawer__anchor--top" />
        <div class="slide-drawer__anchor slide-drawer__anchor--bottom" />
      </div>

      <!-- Drawer content container -->
      <div class="slide-drawer__content">
        <!-- Handle -->
        <div v-if="showHandle" class="slide-drawer__handle-wrapper">
          <div class="slide-drawer__handle" />
        </div>

        <!-- Slot content -->
        <div class="slide-drawer__body">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Base drawer styles using Popover API */
.slide-drawer {
  /* Reset popover defaults */
  border: none;
  padding: 0;
  margin: 0;
  background: transparent;
  max-width: 100%;
  max-height: 100%;
  overflow: visible;

  /* Full viewport positioning */
  position: fixed;
  inset: 0;
  width: 100%;
  height: 100%;

  /* Transitions */
  transition-property: display, overlay;
  transition-behavior: allow-discrete;
  transition-duration: 0.3s;

  /* Backdrop */
  &::backdrop {
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    transition:
      background-color 0.3s,
      backdrop-filter 0.3s,
      display 0.3s,
      overlay 0.3s;
    transition-behavior: allow-discrete;
  }
}

/* Entry animation using @starting-style */
.slide-drawer:popover-open {
  @starting-style {
    &::backdrop {
      background-color: transparent;
      backdrop-filter: blur(0);
      -webkit-backdrop-filter: blur(0);
    }
  }
}

/* Scroller with scroll-snap */
.slide-drawer__scroller {
  height: 100%;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  overflow-y: auto;
  scroll-snap-type: y mandatory;
  overscroll-behavior: contain;
  scroll-behavior: smooth;

  /* Invisible area above drawer for scroll space */
  &::before {
    content: '';
    width: 100%;
    flex: 1 0 100svh;
    order: -1;
    pointer-events: none;
  }
}

/* Snap anchors - invisible elements for snap points */
.slide-drawer__anchors {
  pointer-events: none;
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.slide-drawer__anchor {
  height: 50px;
  width: 100%;
  scroll-snap-align: end;
}

.slide-drawer__anchor--top {
  translate: 0 -100%;
}

/* Drawer content panel */
.slide-drawer__content {
  position: relative;
  width: 100%;
  max-height: calc(90svh - (var(--viewport-offset, 0) * 1px));
  background-color: white;
  border-radius: 1rem 1rem 0 0;
  box-shadow: 0 -10px 40px -10px rgba(0, 0, 0, 0.2);
  scroll-snap-align: end;
  scroll-snap-stop: always;

  /* Entry animation */
  transition:
    translate 0.3s cubic-bezier(0.32, 0.72, 0, 1),
    opacity 0.3s;
}

:root.dark .slide-drawer__content {
  background-color: hsl(240 10% 3.9%);
}

/* Entry animation using @starting-style */
.slide-drawer:popover-open .slide-drawer__content {
  @starting-style {
    translate: 0 100%;
    opacity: 0;
  }
}

/* Handle */
.slide-drawer__handle-wrapper {
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
  cursor: grab;
}

.slide-drawer__handle-wrapper:active {
  cursor: grabbing;
}

.slide-drawer__handle {
  width: 3rem;
  height: 0.25rem;
  border-radius: 9999px;
  background-color: rgba(0, 0, 0, 0.3);
  transition:
    background-color 0.2s,
    width 0.2s;
}

.slide-drawer__content:hover .slide-drawer__handle {
  background-color: rgba(0, 0, 0, 0.4);
}

:root.dark .slide-drawer__handle {
  background-color: rgba(255, 255, 255, 0.3);
}

:root.dark .slide-drawer__content:hover .slide-drawer__handle {
  background-color: rgba(255, 255, 255, 0.4);
}

/* Body content */
.slide-drawer__body {
  overflow-y: auto;
  max-height: calc(90svh - 2.5rem - (var(--viewport-offset, 0) * 1px));
  overscroll-behavior: contain;
  padding-bottom: env(safe-area-inset-bottom, 0);
}
</style>
