import { gsap } from "gsap";

export const LANDING_MOTION_OK = "(prefers-reduced-motion: no-preference)";
export const LANDING_DESKTOP = "(min-width: 1024px)";

let scrollTriggerPromise = null;
let splitTextPromise = null;

export function loadScrollTrigger() {
  if (!scrollTriggerPromise) {
    scrollTriggerPromise = import("gsap/ScrollTrigger").then(({ ScrollTrigger }) => {
      gsap.registerPlugin(ScrollTrigger);
      return ScrollTrigger;
    });
  }
  return scrollTriggerPromise;
}

export function loadSplitText() {
  if (!splitTextPromise) {
    splitTextPromise = import("gsap/SplitText").then(({ SplitText }) => {
      gsap.registerPlugin(SplitText);
      return SplitText;
    });
  }
  return splitTextPromise;
}

/**
 * Scoped GSAP for landing sections. Registers ScrollTrigger (once per
 * session), wraps the setup callback in a gsap.context bound to `scopeRef`
 * (so selector strings only match inside the component) plus a
 * gsap.matchMedia, and reverts both on unmount so SPA navigation never
 * leaves dead triggers behind.
 *
 * setup({ gsap, ScrollTrigger, mm }) runs after mount.
 *
 * Contract: tweens created asynchronously inside an mm.add() handler (after
 * await / nextTick) escape both recording windows and must be killed
 * manually in the handler's cleanup function, guarded by a `cancelled` flag.
 */
export function useLandingGsap(scopeRef, setup) {
  let ctx = null;
  let mm = null;

  onMounted(async () => {
    const ScrollTrigger = await loadScrollTrigger();
    if (!scopeRef.value) return;
    mm = gsap.matchMedia(scopeRef.value);
    ctx = gsap.context(() => setup({ gsap, ScrollTrigger, mm }), scopeRef.value);
  });

  onUnmounted(() => {
    mm?.revert();
    ctx?.revert();
  });

  return { gsap };
}
