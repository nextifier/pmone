# patches/

Patches are applied automatically by pnpm via `pnpm.patchedDependencies` in the
root `package.json`, and each one is pinned to an exact version — bumping that
dependency makes `pnpm install` fail with "patch not applied", which is the
intended signal to re-check whether the patch is still needed.

That mechanism is the reason this directory is empty of patches today: a patch
that grows past a few hunks stops being a patch and becomes an unreviewable fork
of somebody's build output. Past that point, vendor the source instead.

## Active

None. Every patch this project carried has either landed upstream or been
replaced by owned source — see below.

## Dropped

### `reka-ui@2.10.1.patch` — replaced by a vendored Drawer

reka ships a port of Base UI's Drawer, and the port drifts from the original in
ways that show up the moment you swipe. Closing those gaps grew to 36 hunks
across 8 files, six of which were the same three modules duplicated as ESM and
CJS. All of it targeted `dist/`, so none of it was readable in review, and any
version bump would have made `pnpm install` fail with nothing to fall back on.

The Drawer now lives in `app/components/ui/drawer/core/`, vendored from reka's
own `src/` (it ships TypeScript, so the port started from real source rather
than build output) together with the three modules reka does not export:
`DismissableLayer`, `Teleport`, and `useHideOthers`. Everything else it needs —
`Primitive`, `Presence`, `FocusScope`, `useBodyScrollLock`, `createContext`,
`useId`, `useForwardExpose` — is public API and is still imported from `reka-ui`,
so there is exactly one copy of each of those in the bundle.

`aria-hidden` became a direct dependency in the same move: `useHideOthers` needs
it, and it was previously reachable only as a transitive dependency of reka.

reka remains the dependency for every other component in the app. Only its
Drawer is no longer used.

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
