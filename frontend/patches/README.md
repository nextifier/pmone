# patches/

Applied automatically by pnpm via `pnpm.patchedDependencies` in the root
`package.json`. Each patch is pinned to an exact version — bumping that
dependency makes `pnpm install` fail with "patch not applied", which is the
intended signal to re-check whether the patch is still needed.

## Active

### `reka-ui@2.10.1.patch` — Drawer, dist only

reka ships a port of Base UI's Drawer, but the port drifts from the original in
ways that show up as soon as you swipe. The patch closes those gaps; each hunk
mirrors a specific piece of Base UI, so when reka is bumped, check whether the
upstream fix landed before re-applying.

| What | Base UI reference |
| --- | --- |
| `swipeThreshold` forwarded as `max(size * 0.5, 10)`, release velocity `0.5` | `DrawerViewport.tsx: getBaseSwipeThreshold`, `FAST_SWIPE_VELOCITY` |
| Progress is `displacement / size`, not `displacement / (size + threshold)` | `useSwipeDismiss.ts: updateSwipeProgress` |
| Popup height measured border-box, not content-box | `DrawerPopup.tsx: measureHeight` |
| `--drawer-height` only written while nested or closing; `auto` otherwise | `DrawerPopup.tsx: shouldUseAutoHeight` |
| Snap drags past the top stop are sqrt-damped | `useDrawerSnapPoints.ts: getSnapPointSwipeMovement` |
| `snapToNearest` rewritten: velocity ignored in sequential mode, close check after the sequential branch, `shouldForceAdjacent`, reversal correction | `DrawerViewport.tsx: onRelease` |
| Backdrop progress mapped to the snap-point range instead of raw drag | `DrawerViewport.tsx: snapPointRange` |
| Dragging against the dismiss direction rubber-bands (`data-rubber-band`) | `useSwipeDismiss.ts: applyDirectionalDamping` |
| Touch drags may start on buttons, links and fields | `DrawerViewport.tsx: ignoreSelectorWhenTouch: false` |
| `data-*-swipe-ignore` opt-out honoured on both touch and mouse | `DrawerViewport.tsx: isSwipeIgnoredTarget` |
| `data-expanded`, `data-nested-drawer-swiping`, `data-swipe-dismiss` exposed | `DrawerPopupDataAttributes.ts` |
| The parent is told a nested drawer has gone when the child *closes*, not when it unmounts | `DrawerViewport.tsx: nested open state` |

Everything reka gets right is left alone, and the patch only touches `dist/` —
`src/` is shipped but never resolved through the package's exports map.

The last row deserves a note, because it is the one hunk whose effect is easy to
see. reka announces a nested drawer to its parent from `onMounted`/`onUnmounted`,
and the popup stays mounted for the whole 450ms exit, so the parent kept
`data-nested-drawer-open` until the child had finished leaving and only then
un-scaled — two sequential animations instead of one. Measured on coss, the child
gaining `data-ending-style` and the parent losing `data-nested-drawer-open` land
in the same tick. The hunk drives the notification from `rootContext.open` and
keeps a flag so the unmount path cannot decrement the count twice.

The remaining differences live outside the patch, in `components/ui/drawer`:
nested presence/height/progress/swiping are relayed to every ancestor because
reka forwards them to the grandparent's notifier, and `allowSelection` is
implemented with `useDrawerSelectionArea` since reka has no `Drawer.Content`.

## Dropped

### `nuxt@4.5.0.patch` — removed on the 4.5.1 upgrade

Nuxt's plugin transform used to detect a plugin's default export with
`findExports(code).find((e) => e.type === "default" || e.name === "default")`.
`mlly`'s `findExports` returns aliased list exports (`export { plugin as default }`)
as `{ type: "named", names: [...] }` — no `name` field — so the check missed them,
Nuxt logged `[NUXT_B2005] … has no default export and will be ignored at build
time`, and it replaced the plugin body with `export default () => {}`. Nuxt's own
`pages/runtime/plugins/check-if-page-unused.js` compiles to exactly that shape, so
the warning fired on every dev run and Nuxt silently disabled its own page
diagnostics (`NUXT_E4011` / `NUXT_E4016`).

Fixed upstream in Nuxt 4.5.1 (nuxt/nuxt#35676), which changed the check to
`e.names.includes('default')`. The patch is no longer needed.
