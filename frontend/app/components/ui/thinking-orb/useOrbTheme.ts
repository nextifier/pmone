// Ported from thinking-orbs by Jakub Antalik (MIT License).
// https://github.com/Jakubantalik/thinking-orbs
// Vue port of src/theme.ts. Same resolution order: explicit prop →
// ancestor data-theme/.dark|.light class (watched live) →
// prefers-color-scheme (subscribed live). SSR-safe: every listener is
// installed in onMounted; the pre-mount fallback is dark.

import type { MaybeRefOrGetter, Ref } from "vue";
import { onMounted, onScopeDispose, ref, toValue, watch } from "vue";
import type { OrbTheme } from "./types";

function ancestorTheme(el: Element | null): boolean | null {
  let node: Element | null = el;
  while (node) {
    const attr = node.getAttribute("data-theme");
    if (attr === "dark") return true;
    if (attr === "light") return false;
    if (node.classList.contains("dark")) return true;
    if (node.classList.contains("light")) return false;
    node = node.parentElement;
  }
  return null;
}

function systemDark(): boolean {
  return (
    typeof matchMedia === "undefined" ||
    matchMedia("(prefers-color-scheme: dark)").matches
  );
}

/** Resolve the effective dark/light substrate for a mounted element. */
export function useResolvedDark(
  theme: MaybeRefOrGetter<OrbTheme>,
  hostRef: Ref<Element | null | undefined>,
): Ref<boolean> {
  const dark = ref(true);

  let mq: MediaQueryList | null = null;
  let mo: MutationObserver | null = null;
  const onMq = () => resolve();

  function teardown() {
    mq?.removeEventListener("change", onMq);
    mq = null;
    mo?.disconnect();
    mo = null;
  }

  function resolve() {
    const fromTree = ancestorTheme(hostRef.value ?? null);
    dark.value = fromTree ?? systemDark();
  }

  function setup() {
    teardown();

    const mode = toValue(theme);
    if (mode === "dark") {
      dark.value = true;
      return;
    }
    if (mode === "light") {
      dark.value = false;
      return;
    }

    resolve();

    // live OS/browser theme switches
    if (typeof matchMedia !== "undefined") {
      mq = matchMedia("(prefers-color-scheme: dark)");
      mq.addEventListener("change", onMq);
    }

    // live app-level toggles: watch class/data-theme flips on ancestors
    if (typeof MutationObserver !== "undefined" && hostRef.value) {
      mo = new MutationObserver(resolve);
      mo.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ["class", "data-theme"],
        subtree: true,
      });
    }
  }

  onMounted(() => {
    setup();
    watch(() => toValue(theme), setup);
  });
  onScopeDispose(teardown);

  return dark;
}

/** Live `prefers-reduced-motion` — reduced users get a static frame. */
export function useReducedMotion(): Ref<boolean> {
  const reduced = ref(false);

  let mq: MediaQueryList | null = null;
  const on = (e: MediaQueryListEvent) => {
    reduced.value = e.matches;
  };

  onMounted(() => {
    if (typeof matchMedia === "undefined") return;
    mq = matchMedia("(prefers-reduced-motion: reduce)");
    reduced.value = mq.matches;
    mq.addEventListener("change", on);
  });

  onScopeDispose(() => {
    mq?.removeEventListener("change", on);
    mq = null;
  });

  return reduced;
}
