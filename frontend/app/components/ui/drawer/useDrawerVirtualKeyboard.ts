/**
 * Software-keyboard compensation for reka-ui's Drawer.
 *
 * reka ships nothing for this (there is not a single `visualViewport` in its
 * dist). Base UI does, as an opt-in `Drawer.VirtualKeyboardProvider` that writes
 * `--drawer-keyboard-inset` onto `Drawer.Viewport` and leaves the compensating
 * to the consumer's CSS — its own demo only lifts a pinned footer. That works
 * because their popup fills the viewport. Ours is usually `height: auto` and
 * often short, so a keyboard covers the whole thing; the same number is
 * therefore used to shorten `DrawerViewport` from the bottom, which lifts the
 * popup and lowers its `max-h-full` ceiling in one move.
 *
 * Nothing here touches `--drawer-snap-point-offset`, `--drawer-swipe-movement-*`
 * or `--drawer-swipe-baseline-*`: those belong to reka and to the swipe arbiter,
 * and both reset them on several paths.
 *
 * On Chrome with `interactive-widget=resizes-content` this turns itself off: the
 * layout viewport already shrank, so `innerHeight - visualViewport.height` never
 * clears the threshold. iOS has no such option — WebKit has not implemented
 * `interactive-widget` — which is what this exists for.
 */
import type { Ref } from "vue"
import { computed, onScopeDispose, ref, watch } from "vue"

/** Below this the viewport shrank because of browser chrome, not a keyboard. */
const KEYBOARD_RESIZE_THRESHOLD = 60
/** Breathing room between the focused field and the edge of the visible band. */
const KEYBOARD_VISIBILITY_MARGIN = 16
/** The keyboard animates in, so measure again a few times after focus. */
const REALIGN_DELAYS = [0, 150, 300, 450]

/** Input types that raise a keyboard. Matches Base UI's `KEYBOARD_INPUT_TYPES`. */
const KEYBOARD_INPUT_TYPES = new Set(["email", "number", "password", "search", "tel", "text", "url"])

const POPUP_SELECTOR = '[data-slot="drawer-popup"]'
/**
 * `DrawerPanel` renders its content inside a reka ScrollArea, so the element
 * that actually scrolls is the ScrollArea viewport — `[data-slot=drawer-panel]`
 * itself stays `overflow: visible`.
 */
const PANEL_SCROLLER_SELECTOR = "[data-reka-scroll-area-viewport]"

function isKeyboardTarget(node: unknown): node is HTMLElement {
  if (!(node instanceof HTMLElement)) {
    return false
  }
  if (node.isContentEditable || node.tagName === "TEXTAREA") {
    return true
  }
  if (node.tagName !== "INPUT") {
    return false
  }
  return KEYBOARD_INPUT_TYPES.has((node as HTMLInputElement).type)
}

/**
 * Height of the software keyboard, measured from the bottom of the layout
 * viewport. Takes `win` rather than reaching for the global so it can be tested
 * against a fake window — no browser exposes a way to raise a real keyboard.
 */
export function readKeyboardInset(win: Window): number {
  const viewport = win.visualViewport
  if (!viewport) {
    return 0
  }
  // Pinch-zoom moves and shrinks the visual viewport too, and none of it is a keyboard.
  if (viewport.scale !== 1) {
    return 0
  }
  if (win.innerHeight - viewport.height <= KEYBOARD_RESIZE_THRESHOLD) {
    return 0
  }
  const bottom = Math.min(win.innerHeight, Math.max(0, viewport.offsetTop) + viewport.height)
  return Math.max(0, Math.round(win.innerHeight - bottom))
}

export interface UseDrawerVirtualKeyboardOptions {
  /** Consumer opt-out, for drawers that never hold a field. */
  enabled: Ref<boolean>
  /** Listeners live only while the drawer is on screen. */
  active: Ref<boolean>
  /** The popup. Bounds both the focus check and the search for a scroller. */
  element: Ref<HTMLElement | null>
}

export function useDrawerVirtualKeyboard(options: UseDrawerVirtualKeyboardOptions) {
  const { enabled, active, element } = options

  const keyboardInset = ref(0)
  const keyboardVisible = computed(() => keyboardInset.value > 0)

  watch(
    [element, enabled, active],
    ([popup, isEnabled, isActive], _previous, onCleanup) => {
      keyboardInset.value = 0
      if (!popup || !isEnabled || !isActive) {
        return
      }
      const viewport = window.visualViewport
      if (!viewport) {
        return
      }

      let timers: number[] = []
      const clearTimers = () => {
        for (const id of timers) {
          window.clearTimeout(id)
        }
        timers = []
      }

      /**
       * Focus is required to *enter* the compensated state, geometry alone is
       * enough to *stay* in it. The first half is what keeps a drawer opened
       * from an already-focused field on the page behind it from being born
       * lifted and then dropping as that keyboard retracts; the second half
       * keeps a blur inside the drawer from dropping the panel under a keyboard
       * that is still animating away.
       */
      const hasKeyboardFocus = () => {
        const activeElement = document.activeElement
        return isKeyboardTarget(activeElement) && Boolean(activeElement.closest(POPUP_SELECTOR))
      }

      const measure = () => {
        const next = readKeyboardInset(window)
        if (next <= 0) {
          keyboardInset.value = 0
          return
        }
        if (!hasKeyboardFocus() && keyboardInset.value === 0) {
          return
        }
        keyboardInset.value = next
      }

      const findScroller = (target: HTMLElement): HTMLElement | null => {
        const panel = target.closest<HTMLElement>(PANEL_SCROLLER_SELECTOR)
        if (panel && popup.contains(panel)) {
          return panel
        }
        // `<DrawerPanel :scrollable="false">` leaves the scroller to the consumer.
        let node: HTMLElement | null = target
        while (node && node !== popup) {
          const { overflowY } = getComputedStyle(node)
          if ((overflowY === "auto" || overflowY === "scroll") && node.scrollHeight > node.clientHeight) {
            return node
          }
          node = node.parentElement
        }
        return null
      }

      /**
       * A short form of Base UI's `alignFocusedKeyboardTarget`. Their version
       * also pads the scroller so the last field can rise above the keyboard;
       * ours does not need to, because the popup itself has moved up, and adding
       * padding inside the panel would grow the popup's border box and feed
       * reka's ResizeObserver. `scrollIntoView` is avoided for the same reason
       * Base UI avoids it: it scrolls every scrollable ancestor, the document
       * included.
       */
      const align = () => {
        const target = document.activeElement
        if (!isKeyboardTarget(target) || !popup.contains(target)) {
          return
        }
        const scroller = findScroller(target)
        if (!scroller) {
          return
        }

        const scrollerRect = scroller.getBoundingClientRect()
        const keyboardTop = window.innerHeight - keyboardInset.value
        const visibleTop = scrollerRect.top + KEYBOARD_VISIBILITY_MARGIN
        const visibleBottom = Math.min(scrollerRect.bottom, keyboardTop) - KEYBOARD_VISIBILITY_MARGIN
        if (visibleBottom <= visibleTop) {
          return
        }

        const targetRect = target.getBoundingClientRect()
        // Centre rather than reveal by the smallest amount: `DrawerPanel` fades
        // its scroll edges by default, so a field parked against one is half faded out.
        const desired = visibleTop + (visibleBottom - visibleTop - targetRect.height) / 2
        const max = Math.max(0, scroller.scrollHeight - scroller.clientHeight)
        const top = Math.max(0, Math.min(max, scroller.scrollTop + targetRect.top - desired))
        if (Math.abs(top - scroller.scrollTop) < 1) {
          return
        }

        const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches
        scroller.scrollTo({ top, behavior: reduced ? "auto" : "smooth" })
      }

      const realign = () => {
        clearTimers()
        for (const delay of REALIGN_DELAYS) {
          timers.push(
            window.setTimeout(() => {
              measure()
              align()
            }, delay)
          )
        }
      }

      const onViewportChange = () => {
        measure()
        if (keyboardInset.value > 0) {
          align()
        }
      }

      const onFocusIn = (event: FocusEvent) => {
        if (!isKeyboardTarget(event.target) || !popup.contains(event.target)) {
          return
        }
        realign()
      }

      const onFocusOut = () => {
        clearTimers()
        // Do not zero it here: let the latch release once the viewport reports
        // the keyboard has actually gone, so the panel does not drop early.
        timers.push(window.setTimeout(measure, 0))
      }

      viewport.addEventListener("resize", onViewportChange)
      viewport.addEventListener("scroll", onViewportChange)
      document.addEventListener("focusin", onFocusIn, true)
      document.addEventListener("focusout", onFocusOut, true)
      window.addEventListener("resize", measure)

      measure()

      onCleanup(() => {
        clearTimers()
        viewport.removeEventListener("resize", onViewportChange)
        viewport.removeEventListener("scroll", onViewportChange)
        document.removeEventListener("focusin", onFocusIn, true)
        document.removeEventListener("focusout", onFocusOut, true)
        window.removeEventListener("resize", measure)
        keyboardInset.value = 0
      })
    },
    { immediate: true, flush: "post" }
  )

  onScopeDispose(() => {
    keyboardInset.value = 0
  })

  return { keyboardInset, keyboardVisible }
}
