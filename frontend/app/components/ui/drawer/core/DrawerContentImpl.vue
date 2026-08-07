<script lang="ts">
import type {
  DismissableLayerEmits,
  DismissableLayerProps,
} from "./dismissable-layer"

export type DrawerContentImplEmits = DismissableLayerEmits & {
  openAutoFocus: [event: Event]
  closeAutoFocus: [event: Event]
}

export interface DrawerContentImplProps extends DismissableLayerProps {
  trapFocus?: boolean
  /**
   * Initial focus target when the drawer opens.
   * - `true` / default: focus the first focusable element inside
   * - `false`: do not focus anything
   * - element ref: focus that specific element
   */
  initialFocus?: boolean | HTMLElement | null
  /**
   * Final focus target when the drawer closes.
   * - `true` / default: focus the trigger
   * - `false`: do not restore focus
   * - element ref: focus that specific element
   */
  finalFocus?: boolean | HTMLElement | null
}
</script>

<script setup lang="ts">
import type { SwipeDirection } from './utils'
import { useResizeObserver } from '@vueuse/core'
import { computed, onMounted, onUnmounted, watch } from 'vue'
import { DismissableLayer } from "./dismissable-layer"
import { FocusScope } from "reka-ui"
import { useForwardExpose } from "reka-ui"
import { useDrawerSnapPoints } from './composables/useDrawerSnapPoints'
import { useSwipeDismiss } from './composables/useSwipeDismiss'
import { injectDrawerRootContext } from './DrawerRoot.vue'
import { computeSwipeReleaseScalar, DRAWER_CSS_VARS, getDisplacement, registerDrawerCssProperties } from './utils'

const props = defineProps<DrawerContentImplProps>()
const emits = defineEmits<DrawerContentImplEmits>()

const rootContext = injectDrawerRootContext()
const { forwardRef, currentElement } = useForwardExpose()

registerDrawerCssProperties()

// Snap points
const { activeSnapPointOffset, resolvedSnapPoints, snapToNearest } = useDrawerSnapPoints({
  snapPoints: rootContext.snapPoints,
  activeSnapPoint: rootContext.activeSnapPoint,
  popupHeight: rootContext.popupHeight,
  viewportRef: currentElement,
  onSnapPointChange: (point) => {
    if (point === null)
      rootContext.onOpenChange(false)
    else
      rootContext.setActiveSnapPoint(point)
  },
})

/**
 * Backdrop opacity with snap points is not the raw swipe progress: Base UI maps
 * it across the gap between the two tallest stops, so the drawer is fully dimmed
 * at the top stop and clear at every stop below it
 * (`DrawerViewport.tsx:133-158`).
 */
const snapPointRange = computed(() => {
  const pts = resolvedSnapPoints.value
  const dir = rootContext.swipeDirection.value
  if (!pts || pts.length < 2 || (dir !== 'down' && dir !== 'up'))
    return null
  const offsets = pts.map(point => point.offset).sort((a, b) => a - b)
  const range = offsets[1] - offsets[0]
  if (!(range > 0))
    return null
  return { minOffset: offsets[0], range }
})

function offsetToSnapProgress(offset: number) {
  const r = snapPointRange.value
  if (!r)
    return null
  return Math.min(1, Math.max(0, (offset - r.minOffset) / r.range))
}

const snapPointProgress = computed(() => {
  const offset = activeSnapPointOffset.value
  if (offset == null)
    return null
  return offsetToSnapProgress(offset)
})

// A drawer resting on a stop still has a progress; publish it so the backdrop
// settles at the right opacity instead of only tracking live drags.
watch([snapPointProgress, () => rootContext.open.value], ([p, isOpen]) => {
  if (p == null)
    return
  rootContext.onNestedSwipeProgressChange(isOpen ? p : 0)
}, { immediate: true })

// Write the active snap offset to the popup's inline style. Called both via a
// watcher (for live updates as the snap point or popup height changes) and
// explicitly from onMounted (to set the initial value — a lazy watcher would
// only fire on CHANGE, which may never happen on a reopen where popupHeight
// is already measured from a previous open and activeSnapPoint is unchanged).
//
// BaseUI parity: snap points are applied purely via a translate using
// --drawer-snap-point-offset. The popup's height is independent (consumers
// should set max-height: 100dvh or similar and let the transform slide the
// popup visually).
function writeSnapPointOffset() {
  const el = currentElement.value
  if (!el)
    return
  const offset = activeSnapPointOffset.value
  if (offset != null) {
    const dir = rootContext.swipeDirection.value
    const signedOffset = (dir === 'up' || dir === 'left') ? -offset : offset
    el.style.setProperty(DRAWER_CSS_VARS.snapPointOffset, `${signedOffset}px`)
  }
  else {
    el.style.setProperty(DRAWER_CSS_VARS.snapPointOffset, '0px')
  }
}

watch(activeSnapPointOffset, writeSnapPointOffset)

// Measure popup height via ResizeObserver. BaseUI parity: skip writes while a
// nested drawer is open and we already have a measured height, to keep the
// parent's snap-point geometry stable while the child drawer animates.
useResizeObserver(currentElement, ([entry]) => {
  if (!entry)
    return
  if (rootContext.hasNestedDrawer.value && rootContext.popupHeight.value > 0)
    return
  // border-box: `--drawer-height` is consumed as `height` under box-sizing:border-box,
  // so measuring the content box feeds back and shrinks the popup every cycle.
  const h = entry.borderBoxSize?.[0]?.blockSize ?? (entry.target as HTMLElement).offsetHeight
  rootContext.onPopupHeightChange(h)
})

/**
 * `--drawer-height` is only written while the drawer is nested or closing; open
 * and childless it is removed so the panel keeps `height: auto` and can grow
 * with its content. Writing it on every observer tick instead makes the measured
 * height its own input and the value wanders (`DrawerPopup.tsx:
 * shouldUseAutoHeight`).
 */
watch(
  [
    () => rootContext.hasNestedDrawer.value,
    () => rootContext.open.value,
    () => rootContext.popupHeight.value,
  ],
  ([nested, isOpen, measured]) => {
    const el = currentElement.value
    if (!el)
      return
    if (!nested && isOpen)
      el.style.removeProperty(DRAWER_CSS_VARS.height)
    else if (measured > 0)
      el.style.setProperty(DRAWER_CSS_VARS.height, `${measured}px`)
  },
  { immediate: true, flush: 'post' },
)

// Watch frontmostHeight -> set CSS var
watch(() => rootContext.frontmostHeight.value, (h) => {
  currentElement.value?.style.setProperty(DRAWER_CSS_VARS.frontmostHeight, `${h}px`)
})

// Swipe directions: when snap points exist, allow both dismiss AND expand directions
const hasSnapPoints = computed(() => (rootContext.snapPoints.value?.length ?? 0) > 0)
const swipeDirections = computed<SwipeDirection[]>(() => {
  const dismiss = rootContext.swipeDirection.value
  if (!hasSnapPoints.value)
    return [dismiss]
  // Allow swiping in both directions for snap point navigation
  const opposite: Record<string, SwipeDirection> = { up: 'down', down: 'up', left: 'right', right: 'left' }
  return [dismiss, opposite[dismiss]]
})

// Track the latest raw signed delta for snap release (BaseUI parity: the
// release math needs the delta as of the last move, not the accumulated
// CSS offset).
let lastRawDelta = { x: 0, y: 0 }

// Swipe dismiss
const { isSwiping, dragOffset } = useSwipeDismiss({
  enabled: computed(() => rootContext.open.value),
  elementRef: currentElement,
  directions: swipeDirections,
  // Base UI parity: dismiss past half the panel, not a flat 40px.
  swipeThreshold: ({ element, direction }) => Math.max((direction === 'left' || direction === 'right' ? element.offsetWidth : element.offsetHeight) * 0.5, 10),
  movementCssVars: {
    x: DRAWER_CSS_VARS.swipeMovementX,
    y: DRAWER_CSS_VARS.swipeMovementY,
  },
  canStart: () => !rootContext.nestedSwiping.value,
  onDismiss() {
    if (!hasSnapPoints.value) {
      rootContext.onOpenChange(false, 'swipe')
    }
    // With snap points, onRelease handles snapping
  },
  onRelease(velocity) {
    // Write the `--drawer-swipe-strength` CSS var so consumer transitions can
    // scale their duration with release velocity. Ported from BaseUI
    // `DrawerViewport.tsx:resolveSwipeRelease`.
    const el = currentElement.value
    if (el) {
      const dir = rootContext.swipeDirection.value
      const size = (dir === 'left' || dir === 'right') ? el.offsetWidth : el.offsetHeight
      const axisDelta = (dir === 'left' || dir === 'right') ? lastRawDelta.x : lastRawDelta.y
      const releaseVelocity = (dir === 'left' || dir === 'right') ? velocity.x : velocity.y
      const scalar = computeSwipeReleaseScalar({
        direction: dir,
        size,
        axisDelta,
        snapPointOffset: activeSnapPointOffset.value ?? 0,
        releaseVelocity,
      })
      if (scalar != null)
        el.style.setProperty(DRAWER_CSS_VARS.swipeStrength, `${scalar}`)
      else
        el.style.setProperty(DRAWER_CSS_VARS.swipeStrength, '1')
    }

    if (hasSnapPoints.value) {
      // Convert the latest signed delta to a dismiss-direction-positive scalar
      // (BaseUI: `dragDelta = direction==='down' ? deltaY : direction==='up' ? -deltaY : ...`).
      // getDisplacement handles the sign flip per direction.
      const dir = rootContext.swipeDirection.value
      const dragDelta = getDisplacement(dir, lastRawDelta.x, lastRawDelta.y)
      snapToNearest(dragDelta, velocity, dir, rootContext.snapToSequentialPoints.value)

      // Sequencing matters for smooth drag-to-next-snap animation.
      //
      // After snapToNearest, activeSnapPoint has updated synchronously (Vue
      // refs are synchronous) but the `watch(activeSnapPointOffset, ...)`
      // runs on the next microtask. If we cleared --drawer-swipe-movement-y
      // first, the transform would visually jump from the drag position
      // (e.g. translateY(50)) back to the OLD snap offset (translateY(400))
      // for a single frame, then the watch would fire and CSS would
      // transition from 400 down to the new snap offset (translateY(0)) —
      // the user sees a snap-back-then-animate instead of a continuous
      // motion from their finger to the new snap.
      //
      // Fix: write the new snap offset CSS var synchronously FIRST (via
      // writeSnapPointOffset, which reads activeSnapPointOffset.value — the
      // computed recalculates on access), then clear the movement vars.
      // Both writes happen in the same animation frame, and the CSS
      // transition on `transform` smoothly interpolates from the current
      // rendered transform (drag position) to the new snap target.
      if (el) {
        writeSnapPointOffset()
        el.style.setProperty(DRAWER_CSS_VARS.swipeMovementX, '0px')
        el.style.setProperty(DRAWER_CSS_VARS.swipeMovementY, '0px')
      }
    }

    // For snap-point drawers, this callback fully decides the open/close +
    // transform outcome above, so tell finishSwipe to skip its own
    // dismiss-vs-cancel branch.
    return hasSnapPoints.value
  },
  onSwipingChange(swiping) {
    rootContext.onSwipingChange(swiping)
    rootContext.onNestedSwipingChange(swiping)
  },
  onProgress(progress, details) {
    if (details) {
      lastRawDelta = { x: details.deltaX, y: details.deltaY }
    }
    // Past the lowest stop the panel resists instead of following: Base UI's
    // `getSnapPointSwipeMovement` damps the overshoot with a square root.
    if (hasSnapPoints.value && rootContext.swipeDirection.value === 'down') {
      const el = currentElement.value
      if (el && details) {
        const base = activeSnapPointOffset.value ?? 0
        const next = base + details.deltaY
        const movement = next >= 0 ? details.deltaY : -Math.sqrt(-next) - base
        el.style.setProperty(DRAWER_CSS_VARS.swipeMovementY, `${movement}px`)
      }
    }

    let resolvedProgress = progress
    const range = snapPointRange.value
    if (range && rootContext.popupHeight.value > 0 && details && Number.isFinite(details.deltaY)) {
      const base = activeSnapPointOffset.value ?? range.minOffset
      const nextOffset = Math.min(rootContext.popupHeight.value, Math.max(0, base + details.deltaY))
      resolvedProgress = offsetToSnapProgress(nextOffset) ?? progress
    }
    // Gated on `open`, exactly as Base UI does (`DrawerViewport.tsx:194`:
    // `open && shouldTrackProgress ? progress : 0`). Without it a drawer that has
    // been swiped away keeps reporting the value the finger left behind, and the
    // drawer at the back of a three-deep stack loses its peek entirely.
    rootContext.onNestedSwipeProgressChange(rootContext.open.value ? resolvedProgress : 0)
  },
})

// Track the source of the next dismiss so we can attach a reason.
// DismissableLayer fires `escape-key-down` / `pointer-down-outside` / `focus-outside`
// before it fires `dismiss`, so we capture the reason in those handlers and read
// it here.
let pendingDismissReason: 'escape-key' | 'outside-press' | undefined

function onDismiss() {
  if (isSwiping.value)
    return
  rootContext.onOpenChange(false, pendingDismissReason ?? 'outside-press')
  pendingDismissReason = undefined
}

function onEscapeKeyDown(event: KeyboardEvent) {
  pendingDismissReason = 'escape-key'
  emits('escapeKeyDown', event)
}

function onPointerDownOutside(event: any) {
  if (isSwiping.value) {
    event.preventDefault()
    return
  }
  pendingDismissReason = 'outside-press'
  emits('pointerDownOutside', event)
}

function onFocusOutside(event: any) {
  if (isSwiping.value) {
    event.preventDefault()
    return
  }
  emits('focusOutside', event)
}

function onInteractOutside(event: any) {
  if (isSwiping.value) {
    event.preventDefault()
    return
  }
  emits('interactOutside', event)
}

// --- update:openComplete wiring ---
// Fire `update:openComplete` on the popup's own transitionend/animationend,
// not on a microtask — consumers rely on this marker to know the enter/exit
// transition has actually finished. We track the current listener so a rapid
// open→close doesn't leak or double-fire, and guard with `event.target === el`
// so child element transitions don't spuriously resolve the drawer lifecycle.
let openCompleteCleanup: (() => void) | undefined

function clearOpenCompleteListener() {
  openCompleteCleanup?.()
  openCompleteCleanup = undefined
}

watch(() => rootContext.open.value, (isOpen) => {
  clearOpenCompleteListener()
  const el = currentElement.value
  if (!el) {
    // If there's no element (e.g. closed without ever mounting), fire
    // synchronously so consumers still get the contract.
    rootContext.notifyOpenComplete(isOpen)
    return
  }
  const handler = (event: Event) => {
    if (event.target !== el)
      return
    clearOpenCompleteListener()
    rootContext.notifyOpenComplete(isOpen)
  }
  el.addEventListener('transitionend', handler)
  el.addEventListener('animationend', handler)
  openCompleteCleanup = () => {
    el.removeEventListener('transitionend', handler)
    el.removeEventListener('animationend', handler)
  }
})

// Data attributes
const dataAttributes = computed(() => {
  const attrs: Record<string, string | undefined> = {
    'data-state': rootContext.open.value ? 'open' : 'closed',
    'data-swipe-direction': rootContext.swipeDirection.value,
  }
  if (isSwiping.value)
    attrs['data-swiping'] = ''
  if (rootContext.hasNestedDrawer.value)
    attrs['data-nested-drawer-open'] = ''
  if (rootContext.nestedSwiping.value)
    attrs['data-nested-drawer-swiping'] = ''
  if (rootContext.activeSnapPoint.value === 1)
    attrs['data-expanded'] = ''
  return attrs
})

// Sync nestedOpenDrawerCount onto the popup CSS var so nested scale effects
// can read it. BaseUI uses a running count, not a boolean.
watch(() => rootContext.nestedOpenDrawerCount.value, (n) => {
  currentElement.value?.style.setProperty(DRAWER_CSS_VARS.nestedDrawers, `${n}`)
})

// Subscribe to the nested swipe progress store: when a CHILD drawer reports
// dismiss-swipe progress, the parent popup reads it and writes the value onto
// its own `--drawer-swipe-progress` CSS var so consumer CSS can animate the
// parent "in reverse" while the child swipes away. Ported from BaseUI
// `DrawerPopup.tsx` (useIsoLayoutEffect subscribing to nestedSwipeProgressStore).
let unsubscribeNestedProgress: (() => void) | undefined

/**
 * Driven by `open`, not by mount/unmount: the popup stays mounted for the whole
 * exit, so notifying on unmount left the drawer behind scaled back until the
 * front one had finished leaving — a slide followed by a separate un-scale
 * instead of one movement. The flag keeps the unmount path from decrementing a
 * second time.
 */
let notifiedParentNested = false
function setParentNested(next: boolean) {
  if (next === notifiedParentNested)
    return
  notifiedParentNested = next
  rootContext.notifyParentHasNestedDrawer?.(next)
}
watch(() => rootContext.open.value, isOpen => setParentNested(Boolean(isOpen)), { immediate: true })

onMounted(() => {
  rootContext.contentElement.value = currentElement.value

  // Initial nested-drawer count
  currentElement.value?.style.setProperty(
    DRAWER_CSS_VARS.nestedDrawers,
    `${rootContext.nestedOpenDrawerCount.value}`,
  )

  // Initial snap-point offset. Required on reopen when popupHeight is already
  // measured and activeSnapPoint is unchanged — the lazy watcher would never
  // fire and the drawer would render at offset 0 (appearing as snap=1.0).
  writeSnapPointOffset()

  unsubscribeNestedProgress = rootContext.nestedSwipeProgressStore.subscribe(() => {
    const progress = rootContext.nestedSwipeProgressStore.getSnapshot()
    currentElement.value?.style.setProperty(DRAWER_CSS_VARS.swipeProgress, `${progress}`)
  })
})

onUnmounted(() => {
  setParentNested(false)
  unsubscribeNestedProgress?.()
  clearOpenCompleteListener()
})

// Dev warning for missing DrawerTitle
if (process.env.NODE_ENV !== 'production') {
  onMounted(() => {
    if (!document.getElementById(rootContext.titleId)) {
      console.warn(
        `Warning: \`DrawerContent\` requires a \`DrawerTitle\` for accessibility.`,
      )
    }
  })
}
</script>

<template>
  <FocusScope
    as-child
    loop
    :trapped="props.trapFocus"
    @mount-auto-focus="emits('openAutoFocus', $event)"
    @unmount-auto-focus="emits('closeAutoFocus', $event)"
  >
    <DismissableLayer
      :id="rootContext.contentId"
      :ref="forwardRef"
      :as="as"
      :as-child="asChild"
      :disable-outside-pointer-events="disableOutsidePointerEvents"
      role="dialog"
      :aria-describedby="rootContext.descriptionId"
      :aria-labelledby="rootContext.titleId"
      v-bind="{ ...dataAttributes, ...$attrs }"
      @dismiss="onDismiss"
      @escape-key-down="onEscapeKeyDown"
      @focus-outside="onFocusOutside"
      @interact-outside="onInteractOutside"
      @pointer-down-outside="onPointerDownOutside"
    >
      <slot />
    </DismissableLayer>
  </FocusScope>
</template>
