import { computed } from "vue";
import { useMediaQuery } from "@vueuse/core";

/**
 * Theme-aware favicon: the light/dark tab-icon pair, kept in sync with the
 * BROWSER's color scheme.
 *
 * Driven by `prefers-color-scheme`, NOT by the admin's own light/dark toggle:
 * the tab strip is painted by the browser, so a light page inside a dark browser
 * still needs the light-on-dark icon — otherwise the mark disappears into the
 * tab background.
 *
 * Dark is the fallback, so the query asks for LIGHT: `prefers-color-scheme: light`
 * is false on the server and false for browsers that express no preference, which
 * is exactly when the dark icon should show. SSR therefore renders dark; unhead
 * patches both hrefs on the client once the media query resolves, and again on
 * every later OS theme change.
 *
 * Registered through `useHead` rather than VueUse's `useFavicon`: that composable
 * rewrites the href of EVERY `link[rel*="icon"]` in <head> with a single value —
 * a substring match that also catches `rel="apple-touch-icon"` — so it cannot
 * drive a light/dark PAIR, let alone leave the other icons alone.
 *
 * Brand-scoped paths come from useAppConfig(), never a literal: the brand-layer
 * rule (brands/index.ts) keeps shared code free of brand names.
 */
export function useDynamicFavicon() {
  const { app } = useAppConfig();

  // A brand whose public/brands/<id>/ is still empty gets no pair at all, the
  // same guard nuxt.config applies to brandHeadLinks and brandIcons.
  if (!app.assetsReady) {
    return;
  }

  const prefersLight = useMediaQuery("(prefers-color-scheme: light)");
  const variant = computed(() => (prefersLight.value ? "light" : "dark"));

  useHead({
    link: [
      // Preferred by every current browser.
      {
        key: "favicon-svg",
        rel: "icon",
        type: "image/svg+xml",
        href: computed(() => `/brands/${app.brandId}/icons/favicon-${variant.value}.svg`),
      },
      // Fallback for browsers without SVG favicon support (16/32/48 in one file).
      {
        key: "favicon-ico",
        rel: "icon",
        type: "image/x-icon",
        sizes: "16x16 32x32 48x48",
        href: computed(() => `/brands/${app.brandId}/icons/favicon-${variant.value}.ico`),
      },
    ],
  });
}
