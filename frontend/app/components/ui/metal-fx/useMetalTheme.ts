// Theme resolution for MetalFx. Mirrors the resolvers in
// @/components/ui/thinking-orb/useOrbTheme.ts and
// @/components/ui/border-beam/useBeamTheme.ts: explicit prop → ancestor
// data-theme/.dark|.light class (watched live) → prefers-color-scheme
// (subscribed live).
//
// Departure from upstream metal-fx, whose `auto` only reads
// prefers-color-scheme. Dark mode in this monorepo is class-based
// (@nuxtjs/color-mode writes .dark/.light onto <html>), so an OS-only check
// picks the wrong tuning whenever the two disagree.
//
// SSR-safe: every listener is installed in onMounted and the pre-mount value
// is dark, so the server markup and the first client render agree.

import type { MaybeRefOrGetter, Ref } from "vue";
import { onMounted, onScopeDispose, ref, toValue, watch } from "vue";
import type { MetalFxTheme } from "./types";

function ancestorTheme(el: Element | null): "dark" | "light" | null {
  let node: Element | null = el;
  while (node) {
    const attr = node.getAttribute("data-theme");
    if (attr === "dark") return "dark";
    if (attr === "light") return "light";
    if (node.classList.contains("dark")) return "dark";
    if (node.classList.contains("light")) return "light";
    node = node.parentElement;
  }
  return null;
}

function systemTheme(): "dark" | "light" {
  return typeof matchMedia === "undefined" ||
    matchMedia("(prefers-color-scheme: dark)").matches
    ? "dark"
    : "light";
}

/** Resolve the effective dark/light tuning for a mounted element. */
export function useResolvedMetalTheme(
  theme: MaybeRefOrGetter<MetalFxTheme>,
  hostRef: Ref<Element | null | undefined>,
): Ref<"dark" | "light"> {
  const resolved = ref<"dark" | "light">("dark");

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
    resolved.value = ancestorTheme(hostRef.value ?? null) ?? systemTheme();
  }

  function setup() {
    teardown();

    const mode = toValue(theme);
    if (mode === "dark" || mode === "light") {
      resolved.value = mode;
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

  return resolved;
}
