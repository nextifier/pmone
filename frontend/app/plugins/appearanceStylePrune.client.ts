import { STYLE_NAMES } from "@/lib/appearance";
import { useAppearance } from "@/composables/useAppearance";

/**
 * CSSOM pruner for the shadcn "Style" sheets. All 9 `style-X.css` files ship in
 * one bundle (needed for SSR: the first paint must be styled without a client
 * round-trip), but only ONE `.style-X` block is ever active per user. Keeping
 * the other 8 in the CSSOM costs real money at RUNTIME, not just at parse time:
 * ~34k selectors (1.7k of them `:has()`) push a full-document style recalc to
 * ~800ms, which blocks the main thread every time an overlay mounts (dialog /
 * sheet open triggers focus + body scroll-lock → forced full recalc) and eats
 * the entire open animation. Pruning the inactive blocks brings the same recalc
 * down to ~130ms.
 *
 * Mechanics: after hydration, every top-level `.style-X …` rule is deleted from
 * the CSSOM and stashed as text, keyed by style name; the ACTIVE style's rules
 * are re-rendered into a dedicated `<style id="appearance-active-style-rules">`
 * element, wrapped in `@layer base { … }` so the cascade position matches the
 * original `@import … layer(base)` exactly (per-call utility classes must keep
 * winning). Switching styles just re-renders that element from the stash. A
 * head MutationObserver re-harvests after Vite HMR re-injects the full CSS.
 * Everything is wrapped in try/catch: on any failure the app falls back to the
 * unpruned (correct, just slower) stylesheet.
 */

const LIVE_ID = "appearance-active-style-rules";

export default defineNuxtPlugin((nuxtApp) => {
  const { style } = useAppearance();

  /** cssText of every harvested `.style-X` rule, in source order, per style. */
  const stash = new Map<string, string[]>();

  const styleNameOf = (selector: string): string | null => {
    const match = /^\.style-([a-z0-9-]+)/.exec(selector);
    return match && (STYLE_NAMES as readonly string[]).includes(match[1]!)
      ? match[1]!
      : null;
  };

  /**
   * Delete every `.style-X` rule from the CSSOM (except the live element's own)
   * and collect them. Names seen in this pass REPLACE their stash entry, so an
   * HMR re-injection refreshes a style's rules instead of duplicating them.
   * Returns true when anything was harvested.
   */
  const harvest = (): boolean => {
    const pass = new Map<string, string[]>();
    const walk = (group: CSSStyleSheet | CSSGroupingRule): void => {
      let rules: CSSRuleList;
      try {
        rules = group.cssRules;
      } catch {
        return;
      }
      for (let i = rules.length - 1; i >= 0; i--) {
        const rule = rules[i]!;
        const selector = (rule as CSSStyleRule).selectorText;
        const name = selector ? styleNameOf(selector) : null;
        if (name) {
          if (!pass.has(name)) {
            pass.set(name, []);
          }
          pass.get(name)!.unshift(rule.cssText);
          group.deleteRule(i);
        } else if ((rule as CSSGroupingRule).cssRules) {
          walk(rule as CSSGroupingRule);
        }
      }
    };
    for (const sheet of Array.from(document.styleSheets)) {
      if ((sheet.ownerNode as HTMLElement | null)?.id === LIVE_ID) {
        continue;
      }
      walk(sheet);
    }
    for (const [name, rules] of pass) {
      stash.set(name, rules);
    }
    return pass.size > 0;
  };

  const render = (active: string): void => {
    let el = document.getElementById(LIVE_ID) as HTMLStyleElement | null;
    if (!el) {
      el = document.createElement("style");
      el.id = LIVE_ID;
      document.head.appendChild(el);
    }
    const rules = stash.get(active) ?? [];
    el.textContent = rules.length
      ? `@layer base{\n${rules.join("\n")}\n}`
      : "";
  };

  const apply = (): void => {
    try {
      harvest();
      render(style.value);
    } catch {
      /* fall back to the full (unpruned) stylesheet */
    }
  };

  nuxtApp.hook("app:mounted", () => {
    // Defer the initial prune off the critical path: deleting thousands of
    // rules forces one big recalc, better spent in idle time than at mount.
    const idle =
      window.requestIdleCallback?.bind(window) ??
      ((fn: () => void) => setTimeout(fn, 300));
    idle(() => {
      apply();

      watch(style, (name) => {
        try {
          render(name);
        } catch {
          /* noop */
        }
      });

      // Vite HMR (and any late-injected stylesheet) re-adds the full CSS with
      // all 9 style blocks — re-harvest when head gains style/link nodes.
      let timer: ReturnType<typeof setTimeout> | null = null;
      const observer = new MutationObserver((mutations) => {
        const relevant = mutations.some((m) =>
          [...m.addedNodes].some(
            (n) =>
              (n.nodeName === "STYLE" &&
                (n as HTMLElement).id !== LIVE_ID) ||
              n.nodeName === "LINK",
          ),
        );
        if (!relevant) {
          return;
        }
        if (timer) {
          clearTimeout(timer);
        }
        timer = setTimeout(apply, 150);
      });
      observer.observe(document.head, { childList: true, subtree: false });
    });
  });
});
