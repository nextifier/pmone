/**
 * Vue port of Base UI's transition-status machinery
 * (`packages/react/src/internals/useTransitionStatus.ts` and
 * `useAnimationsFinished.ts`), which is what the coss Drawer styles are written
 * against.
 *
 * Base UI animates with CSS *transitions*: on open it renders the element with
 * `data-starting-style`, drops the attribute on the next frame so the transition
 * runs toward the resting style, and on close it adds `data-ending-style` and
 * keeps the element mounted until the animations settle. reka-ui instead uses
 * keyframes plus `data-state`, and its `Presence` decides unmount timing from
 * `animationend` — which never fires for a transition. Porting this lets the
 * coss class strings be used verbatim instead of being reinterpreted.
 *
 * `getAnimations()` covers transitions and animations alike, so waiting on it
 * keeps the unmount correct whichever the consumer's CSS ends up using.
 */
import type { Ref } from "vue";
import { onScopeDispose, ref, watch } from "vue";

export type TransitionStatus = "starting" | "ending" | undefined;

/** Resolves once every running animation and transition on the element settles. */
export function animationsFinished(element: HTMLElement | null): Promise<void> {
  if (!element || typeof element.getAnimations !== "function") {
    return Promise.resolve();
  }
  const animations = element.getAnimations();
  if (animations.length === 0) {
    return Promise.resolve();
  }
  return Promise.all(animations.map((animation) => animation.finished)).then(
    () => undefined,
    () => undefined
  );
}

export interface UseTransitionStatusOptions {
  open: Ref<boolean>;
  /** The element the closing transition runs on; its animations gate the unmount. */
  element: Ref<HTMLElement | null>;
}

export function useTransitionStatus(options: UseTransitionStatusOptions) {
  const { open, element } = options;

  const mounted = ref(open.value);
  const transitionStatus = ref<TransitionStatus>(undefined);

  let openFrame: number | null = null;
  let closeToken = 0;

  function cancelOpenFrame() {
    if (openFrame !== null) {
      cancelAnimationFrame(openFrame);
      openFrame = null;
    }
  }

  watch(
    open,
    (isOpen) => {
      if (isOpen) {
        closeToken += 1;
        mounted.value = true;
        transitionStatus.value = "starting";
        cancelOpenFrame();
        // Two frames: the first commits the starting style, the second releases
        // it so the browser has a previous value to transition away from.
        openFrame = requestAnimationFrame(() => {
          openFrame = requestAnimationFrame(() => {
            openFrame = null;
            transitionStatus.value = undefined;
          });
        });
        return;
      }

      if (!mounted.value) {
        return;
      }

      cancelOpenFrame();
      transitionStatus.value = "ending";
      const token = ++closeToken;
      // Let the ending style land before measuring what is running.
      requestAnimationFrame(() => {
        if (token !== closeToken) {
          return;
        }
        animationsFinished(element.value).then(() => {
          if (token !== closeToken) {
            return;
          }
          mounted.value = false;
          transitionStatus.value = undefined;
        });
      });
    },
    { immediate: true, flush: "post" }
  );

  onScopeDispose(cancelOpenFrame);

  return { mounted, transitionStatus };
}
