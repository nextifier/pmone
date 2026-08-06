/**
 * Cross-axis arbitration for reka-ui's Drawer.
 *
 * reka's own gesture engine only looks for a scrollable ancestor on the *drawer*
 * axis (`useSwipeDismiss.ts`: `const axis = hasVertical ? 'vertical' : 'horizontal'`),
 * so a `left`/`right` drawer never sees the vertical list inside it and kills its
 * scrolling with `preventDefault()` on every move. This composable restores the
 * arbitration Base UI does in `DrawerViewport`, without touching reka.
 *
 * It never drags anything — reka still owns the drag. All it does is decide, once
 * per gesture, whether the move belongs to a cross-axis scroller. When it does, a
 * capture-phase listener on the document calls `stopPropagation()` so reka's
 * bubble-phase listener on the popup never receives the event and never prevents
 * the native scroll.
 *
 * One correction comes with it: reka pins the drag origin at `touchstart` and
 * never re-baselines it (`startSwipe`: `dragStartPos = pos`). Swallowing the moves
 * before the axis lock resolves would therefore make the panel jump by the slop
 * distance on the first move we let through. Since reka only writes CSS variables
 * and `transitions.css` composes the transform, we publish that distance as
 * `--drawer-swipe-baseline-*` and the stylesheet subtracts it.
 */
import type { Ref } from "vue"
import { onScopeDispose, watch } from "vue"

export type DrawerSide = "top" | "right" | "bottom" | "left"

type Axis = "horizontal" | "vertical"

/** Travel before an axis can claim the gesture. */
const AXIS_LOCK_SLOP = 6
/** How far the cross axis must lead the drawer axis to win. */
const AXIS_LOCK_BIAS = 2

/** Opt-out for anything running its own touch gesture (carousels, sliders, sortables). */
const SWIPE_IGNORE_SELECTOR =
  '[data-drawer-swipe-ignore],[data-base-ui-swipe-ignore],[data-swipe-ignore],input[type="range"],[role="slider"]'

function axisOf(side: DrawerSide): Axis {
  return side === "left" || side === "right" ? "horizontal" : "vertical"
}

/**
 * True only when the element both declares an overflow and actually overflows.
 * The style alone is not enough: reka-ui's ScrollArea viewport is permanently
 * `overflow-y: scroll`, and containers using `overflow-auto` report both axes,
 * so a style-only check would find a scroller under every finger.
 */
function isScrollable(element: HTMLElement, axis: Axis): boolean {
  const style = getComputedStyle(element)
  if (axis === "vertical") {
    const { overflowY } = style
    if (overflowY !== "auto" && overflowY !== "scroll") {
      return false
    }
    return element.scrollHeight > element.clientHeight
  }

  const { overflowX } = style
  if (overflowX !== "auto" && overflowX !== "scroll") {
    return false
  }
  return element.scrollWidth > element.clientWidth
}

function findScrollableTouchTarget(
  target: EventTarget | null,
  root: HTMLElement,
  axis: Axis
): HTMLElement | null {
  let node = target instanceof HTMLElement ? target : null
  while (node && node !== root) {
    if (isScrollable(node, axis)) {
      return node
    }
    node = node.parentElement
  }
  return isScrollable(root, axis) ? root : null
}

interface Gesture {
  startX: number
  startY: number
  /** Set once the cross axis has won; the drawer never gets the gesture back. */
  preserveNativeCrossAxisScroll: boolean
  /** Set once the drawer axis has won; arbitration stops re-running. */
  drawerAxisAttributed: boolean
  baselineWritten: boolean
}

export interface UseDrawerSwipeArbiterOptions {
  enabled: Ref<boolean>
  /** The drawer popup. reka's own listeners live here, in the bubble phase. */
  element: Ref<HTMLElement | null>
  side: Ref<DrawerSide>
}

export function useDrawerSwipeArbiter(options: UseDrawerSwipeArbiterOptions): void {
  const { enabled, element, side } = options

  let gesture: Gesture | null = null
  let documentListening = false

  function setBaseline(x: number, y: number) {
    const el = element.value
    if (!el) {
      return
    }
    el.style.setProperty("--drawer-swipe-baseline-x", `${x}px`)
    el.style.setProperty("--drawer-swipe-baseline-y", `${y}px`)
  }

  function clearBaseline() {
    const el = element.value
    if (!el) {
      return
    }
    el.style.removeProperty("--drawer-swipe-baseline-x")
    el.style.removeProperty("--drawer-swipe-baseline-y")
  }

  function stopDocumentListening() {
    if (!documentListening) {
      return
    }
    document.removeEventListener("touchmove", onDocumentTouchMove, { capture: true })
    documentListening = false
  }

  function startDocumentListening() {
    if (documentListening) {
      return
    }
    document.addEventListener("touchmove", onDocumentTouchMove, { passive: false, capture: true })
    documentListening = true
  }

  function endGesture() {
    gesture = null
    stopDocumentListening()
  }

  /**
   * Mirrors Base UI's `shouldYieldTouchMove`. Attribution happens once per
   * gesture; re-running it mid-drag would let the slop checks fire against an
   * origin that is never re-baselined.
   */
  function shouldYield(event: TouchEvent, touch: Touch): boolean {
    if (!gesture) {
      return true
    }
    if (gesture.preserveNativeCrossAxisScroll) {
      return true
    }
    if (gesture.drawerAxisAttributed) {
      return false
    }
    // A non-cancelable move means the browser already committed to a native
    // scroll; claiming it now would drag the panel along with the content.
    if (!event.cancelable) {
      gesture.preserveNativeCrossAxisScroll = true
      return true
    }

    const vertical = axisOf(side.value) === "vertical"
    const drawerDelta = vertical ? touch.clientY - gesture.startY : touch.clientX - gesture.startX
    const crossDelta = vertical ? touch.clientX - gesture.startX : touch.clientY - gesture.startY
    const absDrawer = Math.abs(drawerDelta)
    const absCross = Math.abs(crossDelta)

    if (absCross >= AXIS_LOCK_SLOP && absCross > absDrawer + AXIS_LOCK_BIAS) {
      gesture.preserveNativeCrossAxisScroll = true
      return true
    }
    if (absDrawer >= AXIS_LOCK_SLOP) {
      gesture.drawerAxisAttributed = true
      return false
    }

    // Neither axis has passed the slop, so the gesture cannot be attributed yet.
    // Leave it alone: on iOS, `preventDefault()` on the first cancelable move
    // kills native scrolling for the rest of the gesture.
    return true
  }

  function onDocumentTouchMove(event: TouchEvent) {
    if (!gesture) {
      return
    }
    // Pinch — hand the gesture back and stay out of the way.
    if (event.touches.length > 1) {
      endGesture()
      return
    }
    const touch = event.touches[0]
    if (!touch) {
      return
    }

    if (shouldYield(event, touch)) {
      event.stopPropagation()
      return
    }

    // The drawer axis just won. reka measures from `touchstart`, so the distance
    // already travelled would show up as an instant jump; publish it and let
    // `transitions.css` subtract it.
    if (!gesture.baselineWritten) {
      gesture.baselineWritten = true
      const vertical = axisOf(side.value) === "vertical"
      setBaseline(
        vertical ? 0 : touch.clientX - gesture.startX,
        vertical ? touch.clientY - gesture.startY : 0
      )
    }
  }

  function onTouchStart(event: TouchEvent) {
    endGesture()
    clearBaseline()

    const el = element.value
    if (!enabled.value || !el || event.touches.length !== 1) {
      return
    }
    const touch = event.touches[0]
    if (!touch) {
      return
    }
    const target = event.target instanceof HTMLElement ? event.target : null
    if (target?.closest(SWIPE_IGNORE_SELECTOR)) {
      return
    }

    // Nothing to arbitrate when the finger is not over a cross-axis scroller —
    // reka's own scroll-edge handling already covers the same-axis case. Skipping
    // here also keeps the common bottom drawer free of any added latency.
    const crossAxis: Axis = axisOf(side.value) === "vertical" ? "horizontal" : "vertical"
    if (!findScrollableTouchTarget(target, el, crossAxis)) {
      return
    }

    gesture = {
      startX: touch.clientX,
      startY: touch.clientY,
      preserveNativeCrossAxisScroll: false,
      drawerAxisAttributed: false,
      baselineWritten: false,
    }
    startDocumentListening()
  }

  // `Presence` unmounts the popup while this component stays alive, so the
  // element ref — not the component lifecycle — owns these listeners.
  watch(
    [element, enabled],
    ([el, isEnabled], _previous, onCleanup) => {
      if (!el || !isEnabled) {
        return
      }

      el.addEventListener("touchstart", onTouchStart, { passive: true })
      el.addEventListener("touchend", endGesture)
      el.addEventListener("touchcancel", endGesture)

      onCleanup(() => {
        el.removeEventListener("touchstart", onTouchStart)
        el.removeEventListener("touchend", endGesture)
        el.removeEventListener("touchcancel", endGesture)
        endGesture()
      })
    },
    { immediate: true }
  )

  onScopeDispose(endGesture)
}
