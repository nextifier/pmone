// Theme resolution for BorderBeam. Mirrors the resolver in
// @/components/ui/thinking-orb/useOrbTheme.ts: explicit prop → ancestor
// data-theme/.dark|.light class (watched live) → prefers-color-scheme
// (subscribed live).
//
// This is the one deliberate departure from upstream border-beam, whose
// `auto` only reads prefers-color-scheme. Dark mode in this monorepo is
// class-based (@nuxtjs/color-mode writes .dark/.light onto <html>), so an
// OS-only check picks the wrong palette whenever the two disagree.
//
// SSR-safe: every listener is installed in onMounted; the pre-mount fallback
// is dark, matching upstream's initial state.

import type { MaybeRefOrGetter, Ref } from "vue";
import { onMounted, onScopeDispose, ref, toValue, watch } from "vue";
import type { BorderBeamTheme } from "./types";

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

/** Resolve the effective dark/light substrate for a mounted element. */
export function useResolvedBeamTheme(
  theme: MaybeRefOrGetter<BorderBeamTheme>,
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
