/**
 * Makes the back button/gesture close the drawer instead of navigating away.
 *
 * Nested drawers each push their own history entry, so this has to be a stack.
 * A drawer that listens on its own would close on any `popstate`, including the
 * `history.back()` its child fires while closing — one tap on "Back" inside a
 * nested drawer would take the whole stack down with it. So there is a single
 * listener here, it only ever closes the drawer on top, and the rewind a drawer
 * performs when it closes on its own is marked so it does not read as a back
 * gesture for the one behind it.
 */
import type { Ref } from "vue"
import { onScopeDispose, watch } from "vue"

interface DrawerHistoryEntry {
  close: () => void
}

const stack: DrawerHistoryEntry[] = []
/** Rewinds we asked for ourselves, which must not close anything. */
let pendingSelfPops = 0
let listening = false

function stopListeningWhenEmpty() {
  if (stack.length || !listening) {
    return
  }
  window.removeEventListener("popstate", onPopState)
  listening = false
}

function onPopState() {
  if (pendingSelfPops > 0) {
    pendingSelfPops -= 1
    return
  }
  stack.pop()?.close()
  stopListeningWhenEmpty()
}

export function useDrawerHistory(isOpen: Ref<boolean>): void {
  const entry: DrawerHistoryEntry = {
    close: () => {
      isOpen.value = false
    },
  }

  function push() {
    stack.push(entry)
    window.history.pushState({ drawerOpen: true }, "")
    if (!listening) {
      window.addEventListener("popstate", onPopState)
      listening = true
    }
  }

  function pop() {
    const index = stack.indexOf(entry)
    // Already gone means `onPopState` took it off the stack, so the browser has
    // rewound for us and asking again would eat someone else's entry.
    if (index === -1) {
      return
    }
    stack.splice(index, 1)
    // Only rewind the entry we pushed on open if we are still sitting on it (a
    // normal dismiss). If a navigation has since moved past it — tapping a link
    // inside the drawer, say — `back()` would undo that navigation instead.
    if (window.history.state?.drawerOpen) {
      pendingSelfPops += 1
      window.history.back()
    }
    stopListeningWhenEmpty()
  }

  watch(isOpen, (open, wasOpen) => {
    if (open && !wasOpen) {
      push()
      return
    }
    if (!open && wasOpen) {
      pop()
    }
  })

  onScopeDispose(() => {
    const index = stack.indexOf(entry)
    if (index !== -1) {
      stack.splice(index, 1)
    }
    stopListeningWhenEmpty()
  })
}
