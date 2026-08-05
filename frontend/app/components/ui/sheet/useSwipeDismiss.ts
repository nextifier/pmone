/**
 * Swipe-to-dismiss for `<SheetContent>`, ported from Base UI's Drawer
 * (`useSwipeDismiss.ts` + `DrawerViewport.tsx`). Every constant below is taken
 * from that implementation so the gesture feels identical.
 *
 * The panel can be dragged from anywhere on its body while a scroll container
 * inside it keeps scrolling natively. Nothing sets `touch-action`: a single
 * non-passive capture-phase `touchmove` listener on the document arbitrates
 * between the two, once per gesture.
 *
 * The composable only writes CSS custom properties; `transitions.css` composes
 * them into a transform. That keeps Vue's style patching from wiping an inline
 * transform mid-drag, and lets the `animate-out` keyframe pick the dragged
 * position up as its implicit `from`.
 */
import type { Ref } from "vue"
import { onScopeDispose, watch } from "vue"

export type SheetSide = "top" | "right" | "bottom" | "left"

type SwipeDirection = "up" | "down" | "left" | "right"
type Axis = "horizontal" | "vertical"

const AXIS_LOCK_SLOP = 6
const AXIS_LOCK_BIAS = 2
const MIN_SWIPE_THRESHOLD = 10
const FAST_SWIPE_VELOCITY = 0.5
const MIN_RELEASE_VELOCITY = 0.2
const MAX_RELEASE_VELOCITY = 4
const MIN_RELEASE_DURATION_MS = 80
const MAX_RELEASE_DURATION_MS = 360
const MIN_RELEASE_SCALAR = 0.1
const MAX_RELEASE_SCALAR = 1
const MIN_VELOCITY_DURATION_MS = 50
const MIN_SAMPLE_DURATION_MS = 16
const MAX_SAMPLE_AGE_MS = 80

/** Opt-out for anything that runs its own touch gesture (carousels, sliders, sortables). */
const SWIPE_IGNORE_SELECTOR = '[data-sheet-swipe-ignore],input[type="range"],[role="slider"]'
/** Mouse drags must not start on interactive elements. Touch drags may. */
const POINTER_IGNORE_SELECTOR = 'button,a,input,select,textarea,label,[role="button"]'

const SIDE_TO_DIRECTION: Record<SheetSide, SwipeDirection> = {
  left: "left",
  right: "right",
  top: "up",
  bottom: "down",
}

function clamp(value: number, min: number, max: number): number {
  return Math.min(Math.max(value, min), max)
}

function getDisplacement(direction: SwipeDirection, deltaX: number, deltaY: number): number {
  switch (direction) {
    case "up":
      return -deltaY
    case "down":
      return deltaY
    case "left":
      return -deltaX
    case "right":
      return deltaX
  }
}

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

/**
 * Walks up from the touch target looking for something that actually scrolls on
 * `axis`. Checking the overflow style alone is not enough: `SidebarContent` uses
 * `overflow-auto` on both axes and reka-ui's ScrollArea viewport is permanently
 * `overflow-y: scroll`, so a style-only check would report a horizontal scroller
 * under every finger and the swipe would never start.
 */
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

function getScrollMetrics(element: HTMLElement, axis: Axis) {
  if (axis === "vertical") {
    return {
      offset: element.scrollTop,
      max: Math.max(0, element.scrollHeight - element.clientHeight),
    }
  }
  return {
    offset: element.scrollLeft,
    max: Math.max(0, element.scrollWidth - element.clientWidth),
  }
}

function dismissesFromStartEdge(direction: SwipeDirection, axis: Axis): boolean {
  return axis === "vertical" ? direction === "down" : direction === "right"
}

function isAtSwipeStartEdge(element: HTMLElement, axis: Axis, direction: SwipeDirection): boolean {
  const { offset, max } = getScrollMetrics(element, axis)
  return dismissesFromStartEdge(direction, axis) ? offset <= 0 : offset >= max
}

function canSwipeFromScrollEdge(
  element: HTMLElement,
  axis: Axis,
  direction: SwipeDirection,
  delta: number
): boolean {
  const movingTowardDismiss = dismissesFromStartEdge(direction, axis) ? delta > 0 : delta < 0
  if (!movingTowardDismiss) {
    return false
  }
  return isAtSwipeStartEdge(element, axis, direction)
}

interface Gesture {
  pointerType: "touch" | "mouse"
  pointerId: number
  /** Touch origin, used only by the axis-lock arbitration. */
  startX: number
  startY: number
  lastX: number
  lastY: number
  /** Drag origin, re-baselined to the move that claims the gesture. */
  baseX: number
  baseY: number
  scrollTarget: HTMLElement | null
  hasCrossAxisScrollableContent: boolean
  allowSwipe: boolean | null
  preserveNativeCrossAxisScroll: boolean
  drawerAxisAttributed: boolean
  active: boolean
  offsetX: number
  offsetY: number
  size: number
  startTime: number
  sample: { x: number; y: number; time: number } | null
  velocityX: number
  velocityY: number
}

export interface UseSwipeDismissOptions {
  enabled: Ref<boolean>
  /** The sheet panel. */
  element: Ref<HTMLElement | null>
  /** The overlay. It is a sibling of the panel inside the portal, so it needs its own writes. */
  overlay: Ref<HTMLElement | null>
  side: Ref<SheetSide>
  onDismiss: () => void
}

export function useSwipeDismiss(options: UseSwipeDismissOptions): void {
  const { enabled, element, overlay, side, onDismiss } = options

  let gesture: Gesture | null = null
  let documentListening = false

  function direction(): SwipeDirection {
    return SIDE_TO_DIRECTION[side.value]
  }

  function axis(): Axis {
    const dir = direction()
    return dir === "left" || dir === "right" ? "horizontal" : "vertical"
  }

  function setVar(el: HTMLElement | null, name: string, value: string | null) {
    if (!el) {
      return
    }
    if (value === null) {
      el.style.removeProperty(name)
    } else {
      el.style.setProperty(name, value)
    }
  }

  function setSwiping(swiping: boolean) {
    for (const el of [element.value, overlay.value]) {
      if (!el) {
        continue
      }
      if (swiping) {
        el.setAttribute("data-swiping", "")
      } else {
        el.removeAttribute("data-swiping")
      }
    }
  }

  function writeOffset(x: number, y: number, progress: number) {
    setVar(element.value, "--sheet-swipe-movement-x", `${x}px`)
    setVar(element.value, "--sheet-swipe-movement-y", `${y}px`)
    setVar(overlay.value, "--sheet-swipe-progress", `${progress}`)
  }

  function clearOffset() {
    setVar(element.value, "--sheet-swipe-movement-x", null)
    setVar(element.value, "--sheet-swipe-movement-y", null)
    setVar(overlay.value, "--sheet-swipe-progress", null)
    setVar(element.value, "--sheet-swipe-strength", null)
    setVar(overlay.value, "--sheet-swipe-strength", null)
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

  /** Ends the gesture without deciding anything — used for touchcancel and pinch. */
  function abort() {
    if (!gesture) {
      return
    }
    const wasActive = gesture.active
    gesture = null
    stopDocumentListening()
    setSwiping(false)
    if (wasActive) {
      writeOffset(0, 0, 0)
    }
  }

  function activate(clientX: number, clientY: number, timeStamp: number) {
    const el = element.value
    if (!el || !gesture) {
      return
    }

    // Kill a still-running enter animation so the drag owns the transform. Cancel
    // rather than `style.animation = 'none'`: reka-ui's `usePresence` reads the
    // computed `animation-name` to decide whether to wait for an exit animation,
    // and `none` makes it unmount instantly with no closing motion.
    el.getAnimations().forEach((animation) => animation.cancel())

    gesture.active = true
    gesture.baseX = clientX
    gesture.baseY = clientY
    gesture.startTime = timeStamp
    gesture.size = axis() === "horizontal" ? el.offsetWidth : el.offsetHeight
    gesture.sample = { x: 0, y: 0, time: timeStamp }
    setSwiping(true)
    writeOffset(0, 0, 0)
  }

  function recordSample(x: number, y: number, timeStamp: number) {
    if (!gesture) {
      return
    }
    const previous = gesture.sample
    if (previous && timeStamp > previous.time) {
      const duration = Math.max(timeStamp - previous.time, MIN_SAMPLE_DURATION_MS)
      gesture.velocityX = (x - previous.x) / duration
      gesture.velocityY = (y - previous.y) / duration
    }
    gesture.sample = { x, y, time: timeStamp }
  }

  function feedMove(clientX: number, clientY: number, timeStamp: number) {
    if (!gesture) {
      return
    }
    if (!gesture.active) {
      activate(clientX, clientY, timeStamp)
      return
    }

    const dir = direction()
    const deltaX = clientX - gesture.baseX
    const deltaY = clientY - gesture.baseY

    // 1:1 along the dismiss direction, square-root damping against it.
    const damp = (value: number) => Math.sign(value) * Math.abs(value) ** 0.5
    let offsetX = 0
    let offsetY = 0
    if (axis() === "horizontal") {
      offsetX = (dir === "left" && deltaX < 0) || (dir === "right" && deltaX > 0) ? deltaX : damp(deltaX)
    } else {
      offsetY = (dir === "up" && deltaY < 0) || (dir === "down" && deltaY > 0) ? deltaY : damp(deltaY)
    }

    gesture.offsetX = offsetX
    gesture.offsetY = offsetY
    recordSample(offsetX, offsetY, timeStamp)

    const progress =
      gesture.size > 0 ? clamp(getDisplacement(dir, offsetX, offsetY) / gesture.size, 0, 1) : 0
    writeOffset(offsetX, offsetY, progress)
  }

  /**
   * Scales the exit duration by how fast the panel was flung, so a hard flick
   * finishes quickly and a slow drag past the threshold keeps its usual timing.
   */
  function resolveStrength(size: number, displacement: number, velocity: number): number | null {
    if (size <= 0) {
      return null
    }
    const remaining = Math.max(0, size - displacement)
    if (remaining <= 0 || velocity <= MIN_RELEASE_VELOCITY) {
      return null
    }
    const duration = clamp(
      remaining / clamp(velocity, MIN_RELEASE_VELOCITY, MAX_RELEASE_VELOCITY),
      MIN_RELEASE_DURATION_MS,
      MAX_RELEASE_DURATION_MS
    )
    const normalized =
      (duration - MIN_RELEASE_DURATION_MS) / (MAX_RELEASE_DURATION_MS - MIN_RELEASE_DURATION_MS)
    return MIN_RELEASE_SCALAR + normalized * (MAX_RELEASE_SCALAR - MIN_RELEASE_SCALAR)
  }

  function release(timeStamp: number) {
    if (!gesture) {
      return
    }
    const current = gesture
    gesture = null
    stopDocumentListening()

    if (!current.active) {
      setSwiping(false)
      return
    }

    const dir = direction()
    const { offsetX, offsetY } = current
    const displacement = getDisplacement(dir, offsetX, offsetY)

    const totalDuration = timeStamp > current.startTime ? timeStamp - current.startTime : 0
    const velocityDuration = totalDuration > 0 ? Math.max(totalDuration, MIN_VELOCITY_DURATION_MS) : 0
    const averageVelocity =
      velocityDuration > 0
        ? getDisplacement(dir, offsetX / velocityDuration, offsetY / velocityDuration)
        : 0

    let releaseVelocity = getDisplacement(dir, current.velocityX, current.velocityY)
    const sample = current.sample
    if (sample && timeStamp >= sample.time && timeStamp - sample.time > MAX_SAMPLE_AGE_MS) {
      releaseVelocity = 0
    }

    const threshold = Math.max(current.size * 0.5, MIN_SWIPE_THRESHOLD)
    const shouldDismiss =
      displacement > 0 && (averageVelocity >= FAST_SWIPE_VELOCITY || displacement > threshold)

    if (!shouldDismiss) {
      setSwiping(false)
      writeOffset(0, 0, 0)
      return
    }

    const strength = resolveStrength(
      current.size,
      displacement,
      Math.abs(releaseVelocity) > 0 ? releaseVelocity : averageVelocity
    )
    if (strength !== null) {
      setVar(element.value, "--sheet-swipe-strength", `${strength}`)
      setVar(overlay.value, "--sheet-swipe-strength", `${strength}`)
    }

    // The offset vars stay where the finger left them: the `animate-out` keyframe
    // only declares `to`, so its implicit `from` is the current transform and the
    // exit continues from the dragged position instead of snapping back first.
    setSwiping(false)
    onDismiss()
  }

  function shouldYieldTouchMove(event: TouchEvent, touch: Touch): boolean {
    if (!gesture) {
      return true
    }
    if (gesture.preserveNativeCrossAxisScroll) {
      return true
    }
    // Attribution happens once per gesture; re-running it mid-drag would let the
    // slop checks below fire against an origin that is never re-baselined.
    if (
      gesture.drawerAxisAttributed ||
      gesture.allowSwipe === true ||
      !gesture.hasCrossAxisScrollableContent
    ) {
      return false
    }
    // A non-cancelable move means the browser already committed to a native
    // scroll; claiming it now would drag the panel along with the content.
    if (!event.cancelable) {
      gesture.preserveNativeCrossAxisScroll = true
      return true
    }

    const vertical = axis() === "vertical"
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
    if (!gesture || gesture.pointerType !== "touch") {
      return
    }
    if (event.touches.length > 1) {
      abort()
      return
    }
    const touch = event.touches[0]
    if (!touch) {
      return
    }

    const currentAxis = axis()
    const vertical = currentAxis === "vertical"
    const drawerDelta = vertical ? touch.clientY - gesture.lastY : touch.clientX - gesture.lastX

    if (shouldYieldTouchMove(event, touch)) {
      gesture.lastX = touch.clientX
      gesture.lastY = touch.clientY
      return
    }

    const { scrollTarget } = gesture
    if (!scrollTarget) {
      if (event.cancelable) {
        event.preventDefault()
      }
      event.stopPropagation()
      feedMove(touch.clientX, touch.clientY, event.timeStamp)
    } else if (getScrollMetrics(scrollTarget, currentAxis).max <= 0) {
      // The container matched on style but cannot actually scroll on this axis;
      // stop the window from scrolling instead of swiping.
      if (event.cancelable) {
        event.preventDefault()
      }
      event.stopPropagation()
    } else {
      if (drawerDelta !== 0) {
        if (gesture.allowSwipe !== true) {
          if (
            event.cancelable &&
            canSwipeFromScrollEdge(scrollTarget, currentAxis, direction(), drawerDelta)
          ) {
            gesture.allowSwipe = true
            event.preventDefault()
          } else {
            gesture.allowSwipe = false
          }
        } else if (event.cancelable) {
          event.preventDefault()
        }
      }
      if (gesture.allowSwipe === true) {
        event.stopPropagation()
        feedMove(touch.clientX, touch.clientY, event.timeStamp)
      }
    }

    gesture.lastX = touch.clientX
    gesture.lastY = touch.clientY
  }

  function createGesture(
    pointerType: "touch" | "mouse",
    pointerId: number,
    clientX: number,
    clientY: number,
    target: EventTarget | null,
    timeStamp: number
  ): boolean {
    const el = element.value
    if (!el || !enabled.value) {
      return false
    }
    const node = target instanceof HTMLElement ? target : null
    if (node?.closest(SWIPE_IGNORE_SELECTOR)) {
      return false
    }
    if (pointerType === "mouse" && node?.closest(POINTER_IGNORE_SELECTOR)) {
      return false
    }

    const currentAxis = axis()
    const crossAxis: Axis = currentAxis === "horizontal" ? "vertical" : "horizontal"
    const scrollTarget =
      pointerType === "touch" ? findScrollableTouchTarget(node, el, currentAxis) : null
    const hasCrossAxisScrollableContent =
      pointerType === "touch" ? findScrollableTouchTarget(node, el, crossAxis) !== null : false

    gesture = {
      pointerType,
      pointerId,
      startX: clientX,
      startY: clientY,
      lastX: clientX,
      lastY: clientY,
      baseX: clientX,
      baseY: clientY,
      scrollTarget,
      hasCrossAxisScrollableContent,
      allowSwipe:
        scrollTarget && !isAtSwipeStartEdge(scrollTarget, currentAxis, direction()) ? false : null,
      preserveNativeCrossAxisScroll: false,
      drawerAxisAttributed: false,
      active: false,
      offsetX: 0,
      offsetY: 0,
      size: 0,
      startTime: timeStamp,
      sample: null,
      velocityX: 0,
      velocityY: 0,
    }
    return true
  }

  function onTouchStart(event: TouchEvent) {
    abort()
    if (event.touches.length !== 1) {
      return
    }
    const touch = event.touches[0]
    if (!touch) {
      return
    }
    if (createGesture("touch", touch.identifier, touch.clientX, touch.clientY, event.target, event.timeStamp)) {
      startDocumentListening()
    }
  }

  function onTouchEnd(event: TouchEvent) {
    release(event.timeStamp)
  }

  function onPointerDown(event: PointerEvent) {
    if (event.pointerType !== "mouse" || event.button !== 0) {
      return
    }
    abort()
    if (!createGesture("mouse", event.pointerId, event.clientX, event.clientY, event.target, event.timeStamp)) {
      return
    }

    // Base UI drops an active text selection rather than fighting it.
    const el = element.value
    const selection = document.getSelection()
    if (el && selection && !selection.isCollapsed) {
      const anchor = selection.anchorNode
      const focus = selection.focusNode
      if ((anchor && el.contains(anchor)) || (focus && el.contains(focus))) {
        selection.removeAllRanges()
      }
    }

    try {
      el?.setPointerCapture(event.pointerId)
    } catch {
      // The pointer may already be gone; capture is an optimisation, not a requirement.
    }
  }

  function onPointerMove(event: PointerEvent) {
    if (!gesture || gesture.pointerType !== "mouse" || event.pointerId !== gesture.pointerId) {
      return
    }
    if (event.buttons === 0) {
      release(event.timeStamp)
      return
    }
    // Prevent text selection while dragging with a mouse.
    event.preventDefault()
    feedMove(event.clientX, event.clientY, event.timeStamp)
  }

  function onPointerEnd(event: PointerEvent) {
    if (!gesture || gesture.pointerType !== "mouse" || event.pointerId !== gesture.pointerId) {
      return
    }
    try {
      element.value?.releasePointerCapture(event.pointerId)
    } catch {
      // Already released.
    }
    release(event.timeStamp)
  }

  // `Presence` unmounts the panel while this component stays alive, and the whole
  // `<Sheet>` is torn down when the viewport crosses the mobile breakpoint — so the
  // element ref, not the component lifecycle, owns these listeners.
  watch(
    [element, enabled],
    ([el, isEnabled], _previous, onCleanup) => {
      if (!el || !isEnabled) {
        return
      }

      el.addEventListener("touchstart", onTouchStart, { passive: true })
      el.addEventListener("touchend", onTouchEnd)
      el.addEventListener("touchcancel", abort)
      el.addEventListener("pointerdown", onPointerDown)
      el.addEventListener("pointermove", onPointerMove)
      el.addEventListener("pointerup", onPointerEnd)
      el.addEventListener("pointercancel", onPointerEnd)

      onCleanup(() => {
        el.removeEventListener("touchstart", onTouchStart)
        el.removeEventListener("touchend", onTouchEnd)
        el.removeEventListener("touchcancel", abort)
        el.removeEventListener("pointerdown", onPointerDown)
        el.removeEventListener("pointermove", onPointerMove)
        el.removeEventListener("pointerup", onPointerEnd)
        el.removeEventListener("pointercancel", onPointerEnd)
        gesture = null
        stopDocumentListening()
        clearOffset()
      })
    },
    { immediate: true }
  )

  onScopeDispose(() => {
    gesture = null
    stopDocumentListening()
  })
}
