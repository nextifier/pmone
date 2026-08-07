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
import { useRouter } from "vue-router"

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

/**
 * Bounds the skip loop in `onPopState`. Every skip consumes a history entry so
 * it terminates on its own; this is only here so a browser that answers
 * `history.back()` with a `popstate` that does not move can never spin.
 */
const MAX_CONSECUTIVE_SKIPS = 10
let consecutiveSkips = 0

function claimSelfPop(): boolean {
  const now = Date.now()
  selfPops = selfPops.filter((deadline) => deadline > now)
  if (!selfPops.length) {
    return false
  }
  selfPops.shift()
  return true
}

/**
 * Installed when the first panel opens and never removed. An entry we pushed can
 * outlive every panel — see the skip in `onPopState` — and there is no point at
 * which we can prove none is left, so one passive listener for the life of the
 * app is cheaper than the bookkeeping it would take to be sure.
 */
function startListening() {
  if (listening) {
    return
  }
  window.addEventListener("popstate", onPopState)
  listening = true
}

function goBackSilently() {
  selfPops.push(Date.now() + SELF_POP_TTL_MS)
  window.history.back()
}

function onPopState() {
  const wasOurOwnRewind = claimSelfPop()
  if (!wasOurOwnRewind && stack.length) {
    // Only ever the panel on top: a nested stack comes down one level per press,
    // the same way Escape brings it down.
    consecutiveSkips = 0
    stack.pop()?.close()
    return
  }
  // Nothing of ours is open, so an entry of ours under the cursor is dead. A
  // click that both closes a panel and navigates leaves one behind per panel —
  // three nested drawers leave three — because by the time the panels tear down
  // the router has already pushed and there is no longer anything of ours to
  // rewind. Each carries the URL of the page below it, so left in place they read
  // as back presses that do nothing.
  //
  // The empty-stack test is what makes this safe: it is also what a nested child
  // rewinding onto its live parent's entry looks like, and that entry must not be
  // touched.
  if (!stack.length && sittingOnOwnEntry() && consecutiveSkips < MAX_CONSECUTIVE_SKIPS) {
    consecutiveSkips += 1
    goBackSilently()
    return
  }
  consecutiveSkips = 0
}

/**
 * True while the current entry is the one this module pushed. Everything that
 * rewinds is gated on it: once a navigation has moved past our entry, going back
 * would undo that navigation instead of closing a panel.
 */
function sittingOnOwnEntry(): boolean {
  return Boolean(window.history.state?.panelOpen)
}

/**
 * A link inside a panel closes it *and* navigates, both out of the same click.
 * Rewinding on close would then land on the entry the router is about to build
 * on and cancel the navigation: the panel shuts and the page never changes.
 *
 * Two signals, because neither covers the other. `linkActivatedAt` is set while
 * the click is still being dispatched, which is the only thing early enough —
 * measured on the mobile sidebar the router's own `beforeEach` arrives ~30ms
 * after the panel closes, long after a deferred rewind would have fired. The
 * counter catches the rest: a button that calls `navigateTo()` itself never
 * touches a link.
 */
const LINK_ACTIVATION_WINDOW_MS = 500
let linkActivatedAt = 0
let clickHooked = false
let navigating = 0
let routerHooked = false

function trackLinkActivations() {
  // Runs from `setup`, so unlike everything else here it is reached on the
  // server too, where there is no `window` to listen on.
  if (clickHooked || typeof window === "undefined") {
    return
  }
  clickHooked = true
  // Bubble phase on purpose: `defaultPrevented` only means anything once the
  // link's own handler has run, and that is what separates a router link from
  // an ordinary one — RouterLink cancels the click before pushing.
  window.addEventListener("click", (event) => {
    if (!event.defaultPrevented) {
      return
    }
    if ((event.target as Element | null)?.closest?.("a[href]")) {
      linkActivatedAt = Date.now()
    }
  })
}

function trackRouterNavigations(router: ReturnType<typeof useRouter> | null) {
  if (routerHooked || !router) {
    return
  }
  routerHooked = true
  const done = () => { navigating = Math.max(0, navigating - 1) }
  router.beforeEach(() => { navigating += 1 })
  router.afterEach(done)
  router.onError(done)
}

function navigationIsStarting(): boolean {
  return navigating > 0 || Date.now() - linkActivatedAt < LINK_ACTIVATION_WINDOW_MS
}

function rewind() {
  // Deferred, and both conditions re-checked at the end: the click that closed
  // the panel may still be turning into a navigation. Undoing that navigation
  // costs the user the page they asked for, so we stand down and leave the entry
  // where it is — `onPopState` skips it on the way back.
  setTimeout(() => {
    if (!sittingOnOwnEntry() || navigationIsStarting()) {
      return
    }
    goBackSilently()
  }, 0)
}

export function usePanelHistory(isOpen: Ref<boolean>): void {
  trackLinkActivations()
  trackRouterNavigations(useRouter?.() ?? null)

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
    startListening()
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
  })
}
