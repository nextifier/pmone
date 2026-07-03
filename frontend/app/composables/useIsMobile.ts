import { useMediaQuery } from "@vueuse/core";

/**
 * Shared "is the viewport mobile-width" media query. Hoisted so the many
 * appearance pickers don't each instantiate their own matchMedia listener.
 * SSR-safe (defaults to false on the server).
 */
export function useIsMobile(query = "(max-width: 767px)") {
  return useMediaQuery(query);
}
