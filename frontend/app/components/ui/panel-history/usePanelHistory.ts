/**
 * Makes the back button/gesture close the panel on top instead of navigating
 * away. Shared by Drawer, Dialog and Lightbox: one stack, one listener.
 *
 * Nested drawers each push their own history entry, so this has to be a stack.
 * A panel that listens on its own would close on any `popstate`, including the
 * `history.back()` its child fires while closing — one tap on "Back" inside a
 * nested panel would take the whole stack down with it. So there is a single
 * listener here, it only ever closes the panel on top, and the rewind a panel
 * performs when it closes on its own is marked so it does not read as a back
 * gesture for the one behind it.
 *
 * Neither Base UI nor coss ship anything like this — the drawer has no opinion
 * about history upstream. It exists because on Android the back gesture is how
 * people expect to dismiss a sheet, and without it they leave the page instead.
 */
import type { Ref } from "vue"
import { onScopeDispose, watch } from "vue"

interface PanelHistoryEntry {
  close: () => void
}

/**
 * How long a rewind we asked for stays claimable. `history.back()` is applied
 * asynchronously and is not guaranteed to produce a `popstate` at all — at the
 * very start of the session there is nothing to go back to. A plain counter
 * therefore leaks: one undelivered rewind and every real back press afterwards
 * is swallowed silently, which is exactly how the panel ends up ignoring the
 * back button until a full reload.
 */
const SELF_POP_TTL_MS = 1000

const stack: PanelHistoryEntry[] = []
/** Deadlines for rewinds we asked for ourselves, which must not close anything. */
let selfPops: number[] = []
let listening = false

function claimSelfPop(): boolean {
  const now = Date.now()
  selfPops = selfPops.filter((deadline) => deadline > now)
  if (!selfPops.length) {
    return false
  }
  selfPops.shift()
  return true
}

function stopListeningWhenEmpty() {
  if (stack.length || !listening) {
    return
  }
  window.removeEventListener("popstate", onPopState)
  listening = false
  selfPops = []
}

function onPopState() {
  if (claimSelfPop()) {
    return
  }
  stack.pop()?.close()
  stopListeningWhenEmpty()
}

/**
 * True while the current entry is the one this module pushed. Everything that
 * rewinds is gated on it: once a navigation has moved past our entry, going back
 * would undo that navigation instead of closing a panel.
 */
function sittingOnOwnEntry(): boolean {
  return Boolean(window.history.state?.panelOpen)
}

function rewind() {
  selfPops.push(Date.now() + SELF_POP_TTL_MS)
  window.history.back()
}

export function usePanelHistory(isOpen: Ref<boolean>): void {
  const entry: PanelHistoryEntry = {
    close: () => {
      isOpen.value = false
    },
  }

  function push() {
    stack.push(entry)
    // Spread the existing state rather than replacing it: vue-router keeps its
    // own bookkeeping there (`back`, `current`, `forward`, `position`, `scroll`)
    // and an entry stripped of it loses scroll restoration.
    window.history.pushState({ ...window.history.state, panelOpen: true }, "")
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
    if (sittingOnOwnEntry()) {
      rewind()
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
    if (index === -1) {
      stopListeningWhenEmpty()
      return
    }
    stack.splice(index, 1)
    // Closing a panel tears down every panel nested inside it, and those never
    // get an `open` change on the way out. Without this their entries are left
    // on the history stack, and the next few back presses do nothing visible
    // before the page finally moves.
    if (sittingOnOwnEntry()) {
      rewind()
    }
    stopListeningWhenEmpty()
  })
}
