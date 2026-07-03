import type { AppearanceConfig } from "@/lib/appearance";
import { useDebounceFn } from "@vueuse/core";
import { computed } from "vue";
import {
  appearanceCss,
  DEFAULT_APPEARANCE,
  DEFAULT_STYLE,
  RADIUS_LOCKED_STYLES,
  STYLE_NAMES,
} from "@/lib/appearance";
import { toPresetConfig } from "@/lib/appearance/preset";

/** The persisted appearance selection (short names only — never the token CSS). */
interface AppearanceCookie {
  style?: string;
  baseColor?: string;
  theme?: string;
  chartColor?: string;
  radius?: string;
  font?: string;
  fontHeading?: string;
}

const STYLE_SET = STYLE_NAMES as readonly string[];

/**
 * SINGLE source of truth for ALL theming: color mode (light/dark/system) + the
 * shadcn "Style" (mono/vega/…) + the design tokens (baseColor/theme/chartColor/
 * radius). The header color-mode toggle, the appearance settings page, and every
 * other surface read/write through this one composable — no redundant stores.
 *
 * Everything is COOKIE-backed + SSR-rendered, so the first painted frame is
 * already correct (no flash, no hydration mismatch):
 *  - color mode  → `@nuxtjs/color-mode` configured `storage:"cookie"` (the inline
 *                  anti-FOUC script reads the cookie; SSR resolves it synchronously).
 *  - style       → `<body class="style-X">` via useHead (cookie → SSR).
 *  - tokens      → `<style id="appearance-vars">` via useHead (cookie → SSR).
 * The backend (`user_settings.theme` + `.appearance`) is the cross-device
 * persistence layer; it seeds the cookies on first authed render and is saved
 * (debounced) on change. Token-only — never touches `components/ui`.
 */
export function useAppearance() {
  const colorMode = useColorMode();
  const { user } = useSanctumAuth();
  const sanctumFetch = useSanctumClient();

  const isSyncing = ref(false);
  const syncError = ref(null as string | null);

  // ONE cookie PERSISTS the appearance selection (SSR Set-Cookie + document.cookie).
  // Absent (null) = the user has not customized → native palette + default style.
  const cookieRef = useCookie<AppearanceCookie | null>("pmone-appearance", {
    default: () => null,
    maxAge: 60 * 60 * 24 * 365,
    sameSite: "lax",
  });
  // The LIVE reactive source of truth. `useCookie` returns a fresh, NON-shared ref
  // on every call, so the once-registered `useHead` computeds (below) would observe
  // a different ref than a later `useAppearance()` caller's setters mutate → the
  // preview only updated on reload. `useState` is shared by key across all callers
  // and SSR-serialized, so every surface reads/writes the same reactive value.
  const config = useState<AppearanceCookie | null>(
    "appearance:config",
    () => cookieRef.value,
  );
  // Raw color-mode cookie, used only to detect first-load absence for seeding.
  const colorModeCookie = useCookie<string | null>("pmone-color-mode", {
    default: () => null,
  });

  // ---- Derived state ----------------------------------------------------------
  const style = computed(() => {
    const s = config.value?.style;
    return s && STYLE_SET.includes(s) ? s : DEFAULT_STYLE;
  });
  const hasTokens = computed(() => {
    const c = config.value;
    return !!(
      c && (c.baseColor || c.theme || c.chartColor || c.radius || c.font || c.fontHeading)
    );
  });
  // The full token config for injection (null when not customized).
  const appearance = computed<AppearanceConfig | null>(() =>
    hasTokens.value ? { ...DEFAULT_APPEARANCE, ...config.value } : null,
  );

  // ---- Backend persistence (cross-device); cookie is the live source ----------
  const saveTheme = useDebounceFn(async (theme: string) => {
    if (!user.value) return;
    try {
      isSyncing.value = true;
      syncError.value = null;
      await sanctumFetch("/api/user/settings", {
        method: "PATCH",
        body: { settings: { theme } },
      });
    } catch {
      syncError.value = "Failed to sync theme preference";
    } finally {
      isSyncing.value = false;
    }
  }, 800);

  const saveAppearance = useDebounceFn(async (config: AppearanceCookie | null) => {
    if (!user.value) return;
    try {
      isSyncing.value = true;
      syncError.value = null;
      await sanctumFetch("/api/user/settings", {
        method: "PATCH",
        body: { settings: { appearance: config } },
      });
    } catch {
      syncError.value = "Failed to sync appearance preference";
    } finally {
      isSyncing.value = false;
    }
  }, 800);

  // ---- Setters (the single gate) ---------------------------------------------
  // Kill CSS transitions for one frame around an appearance mutation. Changing a
  // root class/var (.dark, style-X, :root tokens) restyles the WHOLE document; the
  // hundreds of `.cn-*` `transition-colors`/`transition-[color,box-shadow]` then
  // animate their new colors simultaneously → a heavy "sweep" + extra per-frame
  // repaint on top of the recalc. Suppressing transitions for the switch makes it
  // an instant snap (matches shadcn/create + next-themes `disableTransitionOnChange`).
  // App-wide: every surface changes appearance through these setters.
  const runWithoutTransitions = (mutate: () => void) => {
    if (!import.meta.client) {
      mutate();
      return;
    }
    const el = document.documentElement;
    el.classList.add("appearance-switching");
    mutate();
    // Wait for Vue/unhead to flush the class/var change, force a reflow so the new
    // styles apply WITHOUT transition, then re-enable transitions next frame.
    nextTick(() => {
      void document.body.offsetHeight;
      requestAnimationFrame(() =>
        requestAnimationFrame(() => el.classList.remove("appearance-switching")),
      );
    });
  };

  const setColorMode = (mode: string) => {
    runWithoutTransitions(() => {
      colorMode.preference = mode; // module persists to the pmone-color-mode cookie
      if (user.value) saveTheme(mode);
    });
  };

  // Write the LIVE reactive source (`config`, drives the head computeds) AND the
  // cookie (persist to document.cookie + SSR Set-Cookie), then debounce-save to
  // the backend for cross-device sync.
  const writeCookie = (next: AppearanceCookie | null) => {
    runWithoutTransitions(() => {
      config.value = next;
      cookieRef.value = next;
      if (user.value) saveAppearance(next);
    });
  };

  const setStyle = (name: string) => {
    writeCookie({ ...(config.value || {}), style: name });
  };

  // Setting any single token fills the whole config from DEFAULT_APPEARANCE so
  // `appearanceCss` always receives a complete config (and opt-in turns on).
  const setToken = (key: keyof AppearanceConfig, value: string) => {
    const cur = config.value || {};
    writeCookie({
      baseColor: DEFAULT_APPEARANCE.baseColor,
      theme: DEFAULT_APPEARANCE.theme,
      chartColor: DEFAULT_APPEARANCE.chartColor,
      radius: DEFAULT_APPEARANCE.radius,
      font: DEFAULT_APPEARANCE.font,
      fontHeading: DEFAULT_APPEARANCE.fontHeading,
      ...cur,
      [key]: value,
    });
  };
  const setBaseColor = (v: string) => setToken("baseColor", v);
  const setTheme = (v: string) => setToken("theme", v);
  const setChartColor = (v: string) => setToken("chartColor", v);
  const setRadius = (v: string) => setToken("radius", v);
  const setFont = (v: string) => setToken("font", v);
  const setFontHeading = (v: string) => setToken("fontHeading", v);

  /** Revert to native palette + default style. */
  const reset = () => {
    writeCookie(null);
  };

  /** Apply a full selection at once (Shuffle / Open Preset) — one write, opt-in on. */
  const applyConfig = (next: Partial<AppearanceConfig>) => {
    writeCookie(toPresetConfig({ ...config.value, ...next }));
  };

  /**
   * The current selection resolved to the 7 preset fields (defaults for unset).
   * `radius` mirrors the UI: styles that lock radius show/copy "none" so a copied
   * preset always matches the visible chip.
   */
  const presetConfig = computed(() => {
    const resolved = toPresetConfig(config.value);
    resolved.style = style.value;
    const radiusLocked = (RADIUS_LOCKED_STYLES as readonly string[]).includes(resolved.style);
    return radiusLocked ? { ...resolved, radius: "none" } : resolved;
  });

  // ---- Seed cookies from the backend (cross-device, first authed render) ------
  // Runs when the authenticated identity resolves. Because Sanctum runs an
  // initial SSR request, `user` is available server-side, so seeding here writes
  // Set-Cookie on the SSR response → the first paint (incl. the color-mode inline
  // script) already reflects the saved theme. Guarded by cookie-absence so it
  // never overrides a local choice; re-seeds on identity change (impersonation).
  const loadedUserId = useState<string | number | null>(
    "appearance:loaded-uid",
    () => null,
  );
  watch(
    () => user.value?.id,
    (id) => {
      if (!id || id === loadedUserId.value) return;
      loadedUserId.value = id;
      const settings = (user.value as { user_settings?: Record<string, unknown> })
        ?.user_settings;
      if (!settings) return;
      if (!cookieRef.value && settings.appearance) {
        const seeded = { ...(settings.appearance as AppearanceCookie) };
        config.value = seeded;
        cookieRef.value = seeded;
      }
      if (!colorModeCookie.value && typeof settings.theme === "string") {
        colorMode.preference = settings.theme;
      }
    },
    { immediate: true },
  );

  // ---- Head injection (registered once per runtime, app-wide) -----------------
  // Guard on a nuxtApp-instance flag, NOT `useState`: `useState` is serialized
  // from SSR, so on the client it would already be `true` and this block would be
  // SKIPPED — meaning `useHead` never runs client-side and the reactive
  // `<style>`/body-class updates never patch the DOM (preview only changed on
  // reload). The nuxtApp flag is per-runtime (fresh per SSR request, fresh on the
  // client), so `useHead` runs on BOTH — enabling live client updates — while
  // still registering exactly once per environment.
  const nuxtApp = useNuxtApp() as unknown as { _appearanceHeadRegistered?: boolean };
  if (!nuxtApp._appearanceHeadRegistered) {
    nuxtApp._appearanceHeadRegistered = true;
    useHead({
      bodyAttrs: { class: computed(() => `style-${style.value}`) },
      style: [
        {
          id: "appearance-vars",
          // ":root{}" (not "") for the cleared state so useHead reliably patches
          // it back to a no-op rule → native palette restored live on reset.
          innerHTML: computed(() =>
            appearance.value ? appearanceCss(appearance.value) : ":root{}",
          ),
        },
      ],
      meta: [
        {
          name: "theme-color",
          content: computed(() =>
            colorMode.value === "light" ? "#ffffff" : "#09090b",
          ),
        },
      ],
    });
  }

  return {
    colorMode,
    setColorMode,
    style,
    appearance,
    hasTokens,
    setStyle,
    setBaseColor,
    setTheme,
    setChartColor,
    setRadius,
    setFont,
    setFontHeading,
    reset,
    applyConfig,
    presetConfig,
    isSyncing,
    syncError,
  };
}
