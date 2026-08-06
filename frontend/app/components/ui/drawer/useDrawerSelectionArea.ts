/**
 * Marks a region as drawer content, the way Base UI's `Drawer.Content` does.
 *
 * Base UI tags those regions with `data-drawer-content` and skips the mouse
 * swipe when a press lands inside one (`DrawerViewport.tsx: isDrawerContentTarget`),
 * so dragging across text selects it instead of hauling the drawer around. Touch
 * is deliberately left alone — a finger swipe still moves the panel.
 *
 * reka has no equivalent part, and its mouse handler lives on the popup, so
 * stopping the event here keeps it from ever starting a drag.
 */
import type { Ref } from "vue"
import { watch } from "vue"

const CONTENT_ATTRIBUTE = "data-drawer-content"

export function useDrawerSelectionArea(element: Ref<HTMLElement | null>, enabled: Ref<boolean>): void {
  watch(
    [element, enabled],
    ([el, isEnabled], _previous, onCleanup) => {
      if (!el || !isEnabled) {
        return
      }

      const onPointerDown = (event: PointerEvent) => {
        if (event.pointerType !== "touch") {
          event.stopPropagation()
        }
      }

      el.setAttribute(CONTENT_ATTRIBUTE, "")
      el.addEventListener("pointerdown", onPointerDown)

      onCleanup(() => {
        el.removeEventListener("pointerdown", onPointerDown)
        el.removeAttribute(CONTENT_ATTRIBUTE)
      })
    },
    { immediate: true, flush: "post" }
  )
}
