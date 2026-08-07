import type { Ref } from 'vue'
import type { DrawerSnapPoint, SwipeDirection } from '../utils'
import { useEventListener } from '@vueuse/core'
import { computed, ref } from 'vue'

// BaseUI snap-release constants (DrawerViewport.tsx)
const SNAP_VELOCITY_THRESHOLD = 0.5
const SNAP_VELOCITY_MULTIPLIER = 300
const MAX_SNAP_VELOCITY = 4
const FAST_SWIPE_VELOCITY = 0.5
/** Travel below this is the finger settling, not a gesture. */
const MIN_SWIPE_THRESHOLD = 10

export interface ResolvedSnapPoint {
  value: DrawerSnapPoint
  height: number
  offset: number
}

function parseSnapPoint(value: DrawerSnapPoint, viewportHeight: number, rootFontSize: number): number | null {
  let result: number | null = null
  if (typeof value === 'number')
    result = (value >= 0 && value <= 1) ? value * viewportHeight : value
  else if (value.endsWith('rem'))
    result = Number.parseFloat(value) * rootFontSize
  else if (value.endsWith('px'))
    result = Number.parseFloat(value)
  // Unknown units (e.g. '%', 'vh') and non-finite/negative results (e.g.
  // 'abcpx' → NaN, '-10px') are unsupported — drop the snap point so they
  // never flow into the resolved geometry as `NaNpx`/out-of-range offsets.
  if (result == null || !Number.isFinite(result) || result < 0)
    return null
  return Math.round(result)
}

export function useDrawerSnapPoints(options: {
  snapPoints: Ref<DrawerSnapPoint[] | undefined>
  activeSnapPoint: Ref<DrawerSnapPoint | null | undefined>
  popupHeight: Ref<number>
  viewportRef: Ref<HTMLElement | null | undefined>
  onSnapPointChange: (point: DrawerSnapPoint | null) => void
}) {
  const { snapPoints, activeSnapPoint, popupHeight, viewportRef, onSnapPointChange } = options

  const viewportHeight = ref(typeof window !== 'undefined' ? window.innerHeight : 0)
  const rootFontSize = ref(
    typeof document !== 'undefined'
      ? Number.parseFloat(getComputedStyle(document.documentElement).fontSize) || 16
      : 16,
  )

  if (typeof window !== 'undefined') {
    useEventListener(window, 'resize', () => {
      viewportHeight.value = window.innerHeight
    })
  }

  const resolvedSnapPoints = computed<ResolvedSnapPoint[]>(() => {
    const points = snapPoints.value
    if (!points || points.length === 0)
      return []
    const vh = viewportHeight.value
    const fs = rootFontSize.value
    const ph = popupHeight.value

    const resolved: ResolvedSnapPoint[] = []
    for (const pt of points) {
      const height = parseSnapPoint(pt, vh, fs)
      if (height == null)
        continue
      if (resolved.some(r => Math.abs(r.height - height) <= 1))
        continue
      const clampedHeight = Math.min(height, Math.min(ph, vh))
      resolved.push({ value: pt, height: clampedHeight, offset: Math.max(0, ph - clampedHeight) })
    }
    return resolved.sort((a, b) => a.height - b.height)
  })

  /**
   * Resolve the currently-active snap point to its resolved entry, matching by
   * raw value OR by parsed-height equivalence. This lets a controlled drawer use
   * interchangeable representations (`'16rem'` vs `256`, `0.5` vs `400`) without
   * the release math falling back to offset `0` and jumping to the wrong target.
   */
  function findActiveResolvedPoint(): ResolvedSnapPoint | undefined {
    if (!activeSnapPoint.value || resolvedSnapPoints.value.length === 0)
      return undefined
    const activeHeight = parseSnapPoint(
      activeSnapPoint.value as DrawerSnapPoint,
      viewportHeight.value,
      rootFontSize.value,
    )
    return resolvedSnapPoints.value.find(
      r => r.value === activeSnapPoint.value
        || (activeHeight != null && Math.abs(r.height - activeHeight) <= 1),
    )
  }

  const activeSnapPointOffset = computed<number | null>(
    () => findActiveResolvedPoint()?.offset ?? null,
  )

  /**
   * BaseUI-parity snap release math (ported from `DrawerViewport.tsx` lines ~577-714).
   *
   * Takes a **dismiss-signed** drag delta (positive = dismiss direction, negative =
   * open direction) and the raw velocity vector. Computes a velocity-boosted target
   * offset, then either dismisses, snaps to the closest point, or (in sequential
   * mode) advances one step based on physical crossing + fast-swipe velocity.
   */
  function snapToNearest(
    dragDelta: number,
    velocity: { x: number, y: number },
    direction: SwipeDirection,
    sequential: boolean,
  ) {
    const points = resolvedSnapPoints.value
    if (points.length === 0)
      return

    const ph = popupHeight.value

    // Convert raw velocity into a dismiss-signed scalar:
    //   positive = moving in dismiss direction (collapsing)
    //   negative = moving in open direction (expanding)
    const axisVel = (direction === 'up' || direction === 'down') ? velocity.y : velocity.x
    let velSigned = (direction === 'up' || direction === 'left') ? -axisVel : axisVel

    // A flick whose velocity disagrees with the direction the finger actually
    // travelled is the hand settling at the end of the drag, not an instruction.
    // Base UI drops it once the drag is past the noise floor.
    const dragDirection = Math.sign(dragDelta)
    if (dragDirection !== 0 && Math.abs(dragDelta) >= MIN_SWIPE_THRESHOLD) {
      const reversal = Math.sign(velSigned)
      if (reversal !== 0 && reversal !== dragDirection)
        velSigned = 0
    }

    // Current offset (from fully open): 0 = fully open, `ph` = fully closed.
    const activePoint = findActiveResolvedPoint()
    const currentOffset = activePoint?.offset ?? 0
    const dragTargetOffset = Math.max(0, Math.min(ph, currentOffset + dragDelta))

    // Sequential mode ignores the velocity projection entirely. It may only ever
    // move one stop, so throwing the target hundreds of pixels ahead would pick
    // the same neighbour by a longer route — and would skip stops on a hard flick.
    const velocityOffset = Math.abs(velSigned) >= SNAP_VELOCITY_THRESHOLD
      ? Math.max(-MAX_SNAP_VELOCITY, Math.min(MAX_SNAP_VELOCITY, velSigned)) * SNAP_VELOCITY_MULTIPLIER
      : 0
    const targetOffset = sequential
      ? dragTargetOffset
      : Math.max(0, Math.min(ph, dragTargetOffset + velocityOffset))

    const sorted = [...points].sort((a, b) => a.offset - b.offset)
    const nearestIndex = (offset: number) => {
      let index = 0
      let best = Number.POSITIVE_INFINITY
      for (let i = 0; i < sorted.length; i += 1) {
        const d = Math.abs(sorted[i].offset - offset)
        if (d < best) {
          best = d
          index = i
        }
      }
      return index
    }

    if (sequential) {
      const currentIndex = nearestIndex(currentOffset)
      let targetSnapPoint = sorted[nearestIndex(targetOffset)]
      let effectiveTargetOffset = targetOffset
      const velDirection = Math.sign(velSigned)
      const shouldAdvance = dragDirection !== 0
        && velDirection !== 0
        && velDirection === dragDirection
        && Math.abs(velSigned) >= SNAP_VELOCITY_THRESHOLD

      if (shouldAdvance) {
        const adjacentIndex = Math.max(0, Math.min(sorted.length - 1, currentIndex + dragDirection))
        if (adjacentIndex !== currentIndex) {
          const adjacentPoint = sorted[adjacentIndex]
          // Force the neighbour only when the drag has not already carried the
          // target past it, or the flick would pull the drawer backwards.
          const shouldForceAdjacent = dragDirection > 0
            ? targetOffset < adjacentPoint.offset
            : targetOffset > adjacentPoint.offset
          if (shouldForceAdjacent) {
            targetSnapPoint = adjacentPoint
            effectiveTargetOffset = adjacentPoint.offset
          }
        }
        else if (dragDirection > 0) {
          // Already at the lowest stop and still flicking towards dismiss.
          onSnapPointChange(null)
          return
        }
      }

      // The close check sits inside this branch, after the target has resolved.
      // Run up front it would dismiss on a flick that sequential mode was about
      // to turn into a single step.
      if (Math.abs(effectiveTargetOffset - ph) < Math.abs(effectiveTargetOffset - targetSnapPoint.offset)) {
        onSnapPointChange(null)
        return
      }

      onSnapPointChange(targetSnapPoint.value)
      return
    }

    // Free mode: a fast flick towards dismiss closes outright.
    if (velSigned >= FAST_SWIPE_VELOCITY && dragDelta > 0) {
      onSnapPointChange(null)
      return
    }

    const closest = sorted[nearestIndex(targetOffset)]
    if (Math.abs(targetOffset - ph) < Math.abs(targetOffset - closest.offset)) {
      onSnapPointChange(null)
      return
    }
    onSnapPointChange(closest.value)
  }

  return {
    resolvedSnapPoints,
    activeSnapPointOffset,
    viewportHeight,
    snapToNearest,
  }
}
