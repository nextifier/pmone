<script setup lang="ts">
definePageMeta({
  ssr: false,
})

// Elements
const drawerRef = ref<HTMLElement | null>(null)
const scrollerRef = ref<HTMLElement | null>(null)
const slideRef = ref<HTMLElement | null>(null)
const anchorRef = ref<HTMLElement | null>(null)

// Feature detection
const scrollSnapChangeSupport = ref(false)
const scrollAnimationSupport = ref(false)

// Observer and sync state
let observer: IntersectionObserver | null = null
let syncer: number | null = null
let syncs = new Array(10)
let index = 0
let frame = 0

function addNumber(num: number) {
  syncs[index] = num
  index = (index + 1) % syncs.length
}

function syncDrawer() {
  syncer = requestAnimationFrame(() => {
    if (!scrollerRef.value || !slideRef.value) return

    document.documentElement.style.setProperty(
      '--closed',
      String(1 - scrollerRef.value.scrollTop / slideRef.value.offsetHeight),
    )

    if (new Set(syncs).size === 1 && syncs[0] === slideRef.value.offsetHeight) {
      frame++
    }

    if (frame >= 10) {
      frame = 0
      syncs = new Array(10)
      scrollerRef.value?.addEventListener('scroll', scrollDriver, { once: true })
    }
    else {
      addNumber(scrollerRef.value.scrollTop)
      syncDrawer()
    }
  })
}

function scrollDriver() {
  syncDrawer()
}

// IntersectionObserver callback
function observerCallback(entries: IntersectionObserverEntry[]) {
  if (!scrollerRef.value || !slideRef.value || !drawerRef.value) return

  const { isIntersecting, intersectionRatio } = entries[0]
  const isVisible = intersectionRatio === 1

  if (
    !isVisible
    && !isIntersecting
    && scrollerRef.value.scrollTop - (window.visualViewport?.offsetTop || 0)
      < slideRef.value.offsetHeight * 0.5
  ) {
    drawerRef.value.dataset.snapped = 'true'
    ;(drawerRef.value as any).hidePopover()
    observer?.disconnect()
  }
}

// Handle focus out
function handleOut(event: FocusEvent) {
  const target = event.target
  if (!target || !(target instanceof Node) || !drawerRef.value?.contains(target)) {
    window.removeEventListener('focus', handleOut, true)
    ;(drawerRef.value as any)?.hidePopover()
  }
}

// Drawer toggle event handler
function handleToggle(event: ToggleEvent) {
  if (!scrollerRef.value || !anchorRef.value) return

  if (event.newState === 'closed') {
    if (drawerRef.value) {
      drawerRef.value.dataset.snapped = 'false'
    }
    scrollerRef.value.removeEventListener('scroll', scrollDriver)
    if (syncer) cancelAnimationFrame(syncer)
    document.documentElement.style.removeProperty('--closed')
    window.removeEventListener('focus', handleOut, true)
    // Remove scroll lock
    document.body.style.overflow = ''
  }

  if (event.newState === 'open' && !scrollSnapChangeSupport.value) {
    if (!observer) {
      observer = new IntersectionObserver(observerCallback, {
        root: drawerRef.value,
        rootMargin: '0px 0px -1px 0px',
        threshold: 1.0,
      })
    }
    observer.observe(anchorRef.value)
  }

  if (event.newState === 'open' && !scrollAnimationSupport.value) {
    scrollerRef.value.addEventListener('scroll', scrollDriver, { once: true })
  }

  if (event.newState === 'open') {
    window.addEventListener('focus', handleOut, true)
    // Apply scroll lock
    document.body.style.overflow = 'hidden'
  }
}

// Handle overlay click to close drawer
function handleOverlayClick(event: MouseEvent) {
  const target = event.target as HTMLElement
  // Get the drawer content element
  const contentEl = drawerRef.value?.querySelector('.drawer__content')
  // Close drawer if clicking outside of drawer content
  if (contentEl && !contentEl.contains(target)) {
    ;(drawerRef.value as any)?.hidePopover()
  }
}

// Scroll snap change handler
function handleScrollSnapChange() {
  if (scrollerRef.value && scrollerRef.value.scrollTop === 0) {
    if (drawerRef.value) {
      drawerRef.value.dataset.snapped = 'true'
      ;(drawerRef.value as any).hidePopover()
    }
  }
}

// Attach drag functionality for desktop
function attachDrag(element: HTMLElement) {
  let startY = 0
  let drag = 0
  let scrollStart = 0

  const reset = () => {
    startY = drag = 0
    if (!scrollerRef.value) return

    const top = scrollerRef.value.scrollTop < scrollStart * 0.5 ? 0 : scrollStart

    const handleScroll = () => {
      if (scrollerRef.value?.scrollTop === top) {
        document.documentElement.dataset.dragging = 'false'
        scrollerRef.value?.removeEventListener('scroll', handleScroll)
      }
    }

    scrollerRef.value.addEventListener('scroll', handleScroll)
    scrollerRef.value.scrollTo({ top, behavior: 'smooth' })
    handleScroll()
  }

  const handle = ({ y }: { y: number }) => {
    drag += Math.abs(y - startY)
    scrollerRef.value?.scrollTo({
      top: scrollStart - (y - startY),
      behavior: 'instant' as ScrollBehavior,
    })
  }

  const teardown = (event: MouseEvent) => {
    if ((event.target as HTMLElement).tagName !== 'BUTTON') {
      reset()
    }
    document.removeEventListener('mousemove', handle)
    document.removeEventListener('mouseup', teardown)
  }

  const activate = ({ y }: { y: number }) => {
    startY = y
    scrollStart = scrollerRef.value?.scrollTop || 0
    document.documentElement.dataset.dragging = 'true'
    document.addEventListener('mousemove', handle)
    document.addEventListener('mouseup', teardown)
  }

  element.addEventListener('click', (event) => {
    if (drag > 5) event.preventDefault()
    reset()
  })

  element.addEventListener('mousedown', activate)
}

// Handle VisualViewport changes for iOS keyboard
function handleResize() {
  document.documentElement.style.setProperty(
    '--sw-keyboard-height',
    String(window.visualViewport?.offsetTop || 0),
  )
}

onMounted(() => {
  // Feature detection
  scrollSnapChangeSupport.value = 'onscrollsnapchange' in window
  scrollAnimationSupport.value = CSS.supports('animation-timeline: scroll()')

  // Setup scroll snap change listener if supported
  if (scrollSnapChangeSupport.value && scrollerRef.value) {
    scrollerRef.value.addEventListener('scrollsnapchange', handleScrollSnapChange)
  }

  // Attach drag functionality
  if (drawerRef.value) {
    attachDrag(drawerRef.value)
  }

  // Visual viewport resize handler
  window.visualViewport?.addEventListener('resize', handleResize)
})

onUnmounted(() => {
  if (scrollerRef.value) {
    scrollerRef.value.removeEventListener('scrollsnapchange', handleScrollSnapChange)
  }
  window.visualViewport?.removeEventListener('resize', handleResize)
  observer?.disconnect()
  if (syncer) cancelAnimationFrame(syncer)
})
</script>

<template>
  <main class="min-h-screen bg-background drawer-page">
    <main>
      <h1 class="fluid">drawer.</h1>
      <p>requested by the community. built for the community.</p>
      <button
        class="main-open"
        popovertargetaction="toggle"
        popovertarget="drawer"
      >
        slide open
      </button>
      <span class="arrow">
        <svg
          aria-hidden="true"
          viewBox="0 0 144 141"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M129.189 0.0490494C128.744 0.119441 126.422 0.377545 124.03 0.635648C114.719 1.6446 109.23 2.4893 108.058 3.09936C107.119 3.56864 106.674 4.34295 106.674 5.44576C106.674 6.71281 107.424 7.51058 109.043 7.97986C110.403 8.37875 110.825 8.42567 118.87 9.52847C121.778 9.92736 124.288 10.3028 124.475 10.3732C124.663 10.4436 122.951 11.1006 120.676 11.8749C110.028 15.4414 100.412 20.7677 91.7339 27.9242C88.38 30.7164 81.6957 37.4271 79.2096 40.5009C73.8387 47.2116 69.6874 54.8139 66.5681 63.7302C65.9348 65.4665 65.3484 66.8978 65.2546 66.8978C65.1374 66.8978 63.7771 66.7336 62.2291 66.5693C52.9649 65.5134 43.1847 68.1649 34.1316 74.2186C24.7735 80.46 18.5349 87.7338 10.5371 101.742C2.53943 115.726 -1.0959 127.482 0.287874 135.014C0.89767 138.463 2.0469 140.035 3.97011 140.082C5.28352 140.105 5.37733 139.659 4.20465 139.049C3.05541 138.463 2.6567 137.9 2.32835 136.281C0.616228 128.021 6.24512 113.028 17.4325 96.1104C23.2725 87.241 28.362 81.9147 35.5622 77.1046C43.8649 71.5437 52.7069 69.033 61.1737 69.8308C64.9967 70.1828 64.6917 69.9247 64.1992 72.4822C62.2525 82.5013 63.8005 92.6378 67.9753 97.354C73.1116 103.079 81.9771 102 85.0027 95.2657C86.3395 92.2858 86.3864 87.7103 85.1434 83.9796C83.1498 78.0901 80.007 73.8197 75.4335 70.8163C73.8152 69.7604 70.4848 68.1883 69.875 68.1883C69.359 68.1883 69.4294 67.6487 70.2268 65.3257C72.3377 59.2486 75.457 52.7021 78.4122 48.244C83.2436 40.9232 91.4524 32.5701 99.1687 27.103C105.806 22.4102 113.241 18.5386 120.512 16.0045C123.772 14.8548 129.87 13.1889 130.081 13.3766C130.128 13.447 129.541 14.362 128.791 15.4414C124.78 21.0258 122.716 26.0706 122.388 30.998C122.224 33.7198 122.341 34.588 122.88 34.2595C122.998 34.1891 123.678 32.969 124.405 31.5611C126.281 27.8069 131.722 20.6738 139.579 11.6402C141.127 9.85697 142.652 7.86254 143.027 7.08823C144.552 4.03792 143.52 1.48035 140.377 0.471397C139.439 0.166366 138.102 0.0490408 134.584 0.0255769C132.074 -0.021351 129.635 0.00212153 129.189 0.0490494ZM137.117 4.92955C137.187 5.0234 136.718 5.63346 136.061 6.29045L134.865 7.48712L131.042 6.73627C128.931 6.33739 126.727 5.9385 126.14 5.8681C124.827 5.68039 124.123 5.32843 124.968 5.28151C125.296 5.28151 126.868 5.11725 128.486 4.953C131.3 4.64797 136.812 4.62451 137.117 4.92955ZM71.5168 72.5292C76.2075 74.899 79.4441 78.8175 81.3204 84.355C83.6189 91.1361 81.2266 96.8378 76.0433 96.8847C73.3227 96.9082 70.9773 95.2188 69.5936 92.2389C68.2802 89.4232 67.6938 86.5606 67.5765 82.1259C67.4593 78.3248 67.6 76.4242 68.2333 72.7403L68.4912 71.2856L69.359 71.5906C69.8515 71.7548 70.8132 72.1772 71.5168 72.5292Z"
            fill="currentColor"
          />
        </svg>
        <span>do it.</span>
      </span>
    </main>

    <aside
      id="drawer"
      ref="drawerRef"
      class="drawer"
      popover="auto"
      @toggle="handleToggle"
    >
      <div ref="scrollerRef" class="drawer__scroller" @click="handleOverlayClick">
        <div ref="slideRef" class="drawer__slide">
          <div class="drawer__anchors">
            <div ref="anchorRef" class="drawer__anchor" />
            <div class="drawer__anchor" />
          </div>
          <!-- Acts like a backdrop button so you don't body click -->
          <button
            popovertargetaction="hide"
            popovertarget="drawer"
            class="drawer__curtain"
            tabindex="-1"
          />
          <div class="drawer__content">
            <button
              autofocus
              class="drawer__drag"
              popovertargetaction="hide"
              popovertarget="drawer"
            >
              <span />
              <span>it's just a drawer™</span>
            </button>
            <div class="content">
              <p>
                a drawer demo made with modern web platform features as requested
                by the community.
              </p>
              <ul>
                <li>Popover API</li>
                <li>onscrollsnapchange</li>
                <li>CSS scroll snap</li>
                <li>CSS scroll-driven animations</li>
                <li>CSS @starting-style</li>
                <li>interactive-widget=resizes content</li>
              </ul>
              <p>
                check out
                <a
                  href="https://craftofui.substack.com"
                  target="_blank"
                  rel="noopener noreferrer"
                >the Craft of UI</a>
                to learn more.
              </p>
            </div>
          </div>
        </div>
      </div>
    </aside>
  </main>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Gloria+Hallelujah&display=swap');

/* ===== BASE LAYER ===== */
.drawer-page {
  --font-size-min: 16;
  --font-size-max: 20;
  --font-ratio-min: 1.2;
  --font-ratio-max: 1.33;
  --font-width-min: 375;
  --font-width-max: 1500;
}

.drawer-page :where(.fluid) {
  --fluid-min: calc(
    var(--font-size-min) * pow(var(--font-ratio-min), var(--font-level, 0))
  );
  --fluid-max: calc(
    var(--font-size-max) * pow(var(--font-ratio-max), var(--font-level, 0))
  );
  --fluid-preferred: calc(
    (var(--fluid-max) - var(--fluid-min)) /
      (var(--font-width-max) - var(--font-width-min))
  );
  --fluid-type: clamp(
    (var(--fluid-min) / 16) * 1rem,
    ((var(--fluid-min) / 16) * 1rem) -
      (((var(--fluid-preferred) * var(--font-width-min)) / 16) * 1rem) +
      (var(--fluid-preferred) * var(--variable-unit, 100vi)),
    (var(--fluid-max) / 16) * 1rem
  );
  font-size: var(--fluid-type);
}

.drawer-page *,
.drawer-page *:after,
.drawer-page *:before {
  box-sizing: border-box;
}

.drawer-page {
  display: grid;
  place-items: center;
  min-height: 100svh;
  font-family: inherit;
  background: var(--background);
  color: var(--foreground);
}

.drawer-page main {
  background: var(--background);
}

.drawer-page main::before {
  --size: 45px;
  --line: var(--border);
  content: '';
  height: 100svh;
  width: 100%;
  position: fixed;
  background: linear-gradient(
      90deg,
      var(--line) 1px,
      transparent 1px var(--size)
    )
      50% 50% / var(--size) var(--size),
    linear-gradient(var(--line) 1px, transparent 1px var(--size)) 50% 50% /
      var(--size) var(--size);
  mask: linear-gradient(-20deg, transparent 50%, white);
  top: 0;
  transform-style: flat;
  pointer-events: none;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border-width: 0;
}

/* ===== AESTHETIC LAYER ===== */
.drawer-page {
  --duration: 0.5s;
  --ease: cubic-bezier(0.32, 0.72, 0, 1);
  --drag-bar: 44px;
  --drawer-border: var(--border);
  --drawer-bg: var(--popover);
  --drawer-color: var(--popover-foreground);
  --handle-bg: var(--muted-foreground);
}

.drawer-page main {
  align-content: center;
}

.drawer ul {
  list-style-position: inside;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.drawer-page main > p {
  text-wrap: balance;
  text-align: center;
  max-width: calc(100% - 2rem);
  color: var(--muted-foreground);
}

.drawer-page h1 {
  --font-level: 7;
  margin: 0;
}

.main-open {
  border-radius: 12px;
  padding: 1rem 2rem;
  border: 1px solid var(--border);
  cursor: pointer;
  background: var(--secondary);
  color: var(--secondary-foreground);
  font-weight: 500;
  transition: background-color 0.2s, border-color 0.2s;
}

.main-open:hover {
  background: var(--muted);
  border-color: var(--muted-foreground);
}

.arrow {
  font-family: 'Gloria Hallelujah', cursive;
  width: 100px;
  position: relative;
  translate: 100% 0;
  margin-top: 2rem;
  transform-origin: 0 0;
  rotate: 5deg;
  color: var(--muted-foreground);
}

.content {
  padding: 1rem;
  display: flex;
  flex-direction: column;
}

.drawer a {
  text-decoration-thickness: 2px;
  text-underline-offset: 2px;
  color: inherit;
  opacity: var(--active, 0.75);
}

.drawer a:is(:hover, :focus-visible) {
  --active: 1;
}

.arrow svg {
  scale: -1 1;
}

.arrow span {
  white-space: nowrap;
  rotate: -20deg;
  display: inline-block;
  position: absolute;
  left: 100%;
  top: 100%;
  translate: -25% 50%;
}

.drawer__content {
  border: 1px solid var(--drawer-border);
  border-bottom: 0;
  border-radius: 12px 12px 0 0;
  color: var(--drawer-color);
  background: var(--drawer-bg);
}

.drawer-page button {
  border-radius: 0;
  color: inherit;
  -webkit-appearance: none;
  outline: #0000;
}

.drawer-page main {
  width: 100%;
  height: 100svh;
  display: grid;
  place-items: center;
}

.drawer__drag span:first-of-type {
  width: 8ch;
  height: 6px;
  border-radius: 10px;
  background: var(--handle-bg);
  opacity: 0.4;
  transition: opacity 0.2s;
}

.drawer__content:hover .drawer__drag span:first-of-type {
  opacity: 0.6;
}

.drawer__drag {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: space-evenly;
  height: var(--drag-bar);
  background: transparent;
  border: 0;
  font-size: 0.875rem;
  outline-color: transparent;
  border-bottom: 1px solid var(--drawer-border);
  cursor: grab;
  color: var(--muted-foreground);
}

.drawer__drag:active {
  cursor: grabbing;
}

.drawer__content {
  padding-top: var(--drag-bar);
}

/* ===== ANIMATION LAYER ===== */
.drawer-page {
  timeline-scope: --drawer;
}

.drawer__slide {
  view-timeline: --drawer;
}

.drawer:not([data-snapped='true']) {
  transition-property: display, overlay;
  transition-behavior: allow-discrete;
  transition-duration: var(--duration);
}

.drawer__content {
  transition-property: translate;
  transition-duration: var(--duration);
  transition-timing-function: var(--ease);
  translate: 0 100%;
}

.drawer:popover-open .drawer__content {
  translate: 0 0;
}

@starting-style {
  .drawer:popover-open .drawer__content {
    translate: 0 100%;
  }
}

@property --opened {
  syntax: '<number>';
  inherits: true;
  initial-value: 0;
}

@property --closed {
  syntax: '<number>';
  inherits: true;
  initial-value: 1;
}

.drawer-page main {
  transition-property: --opened, --closed;
  transition-duration: var(--duration);
  transition-timing-function: var(--ease);
  transform-origin: 50% 0%;
}

.drawer-page:has(.drawer:popover-open) main {
  transition-property: --opened;
}

.drawer-page main {
  --diff: calc(var(--opened) * var(--closed));
  --scale-down: 0.04;
  --rad: 12px;
  --ty: calc(env(safe-area-inset-top) + var(--rad));
  scale: calc(
    1 -
      ((var(--opened) * var(--scale-down)) - (var(--diff) * var(--scale-down)))
  );
  border-radius: calc(
    (var(--opened) * var(--rad)) - (var(--diff) * var(--rad))
  );
  translate: 0 calc((var(--opened) * var(--ty)) - (var(--diff) * var(--ty)));
}

.drawer-page:has(.drawer:popover-open) main {
  overflow: hidden;
  --opened: 1;
}

.drawer-page:has(.drawer:popover-open) {
  --closed: 0;
}

@supports (animation-timeline: scroll()) {
  .drawer-page:has(.drawer:popover-open) {
    --closed: 1;
    animation: open both linear reverse;
    animation-timeline: --drawer;
    animation-range: entry;
  }

  @keyframes open {
    0% {
      --closed: 0;
    }
  }
}

.drawer-page:has(.drawer:popover-open),
.drawer-page:has(.drawer:popover-open) main,
.drawer:popover-open {
  overflow: hidden;
  overscroll-behavior: none;
}

.drawer::backdrop {
  transition-property: display, --opened, --closed, overlay;
  transition-behavior: allow-discrete;
  transition-duration: var(--duration);
  transition-timing-function: var(--ease);
  opacity: calc(var(--opened) - (var(--opened) * var(--closed)));
}

.drawer:popover-open::backdrop {
  --opened: 1;
}

@starting-style {
  .drawer:popover-open::backdrop {
    --opened: 0;
  }
}

/* ===== DEMO LAYER ===== */
.drawer {
  inset: 0 0 0 0;
  margin: 0;
  width: unset;
  height: unset;
  border: 0;
  padding: 0;
  background: transparent;
  transition: inset var(--duration) var(--ease);
}

.drawer::backdrop {
  background: hsl(0 0% 0% / 0.5);
}

[data-dragging='true'] .drawer__scroller {
  scroll-snap-type: none;
}

@media (hover: none) and (pointer: coarse) {
  .drawer__slide::after {
    content: '';
    position: absolute;
    inset: 0;
    background: var(--background);
    translate: 0 100%;
  }
}

.drawer__scroller {
  height: 100%;
  width: 100%;
  flex-direction: column;
  align-items: center;
  display: flex;
  overflow-y: auto;
  overscroll-behavior: none;
  scroll-snap-type: y mandatory;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.drawer__scroller::-webkit-scrollbar {
  display: none;
}

.drawer__scroller::after {
  content: '';
  width: 100%;
  height: 100svh;
  order: -999999;
  flex: 1 0 100svh;
}

.drawer__slide {
  --size: 600px;
  width: 600px;
  max-height: calc(95% - (var(--sw-keyboard-height, 0) * 1px));
  max-width: 100%;
  height: var(--size);
  flex: 1 0 var(--size);
  position: relative;
}

.drawer__anchors {
  pointer-events: none;
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  z-index: 10;
}

.drawer__anchor {
  height: 50px;
  width: 100%;
  scroll-snap-align: end;
}

.drawer__anchor:first-of-type {
  translate: 0 -100%;
}

.drawer__curtain {
  position: absolute;
  left: 50%;
  opacity: 0;
  height: 100svh;
  width: 100%;
  bottom: 0;
  border: 0;
  translate: -50% 0;
}

.drawer__content {
  width: 100%;
  height: 100%;
  z-index: 2;
  position: absolute;
}

.drawer__content {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.drawer__drag {
  position: absolute;
  top: 0;
  width: 100%;
}

.content {
  flex: 1;
  overflow: auto;
}
</style>
