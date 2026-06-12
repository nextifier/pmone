/**
 * Shared visibility state for landing sections.
 *
 * `inView` gates looping CSS animations (paused while offscreen via the
 * `.is-inview` class); `seen` latches once for one-shot entrance animations
 * that must not replay when the section scrolls back in (`.is-seen`).
 */
export function useSectionVisibility(target) {
  const inView = useElementVisibility(target);
  const seen = ref(false);

  watch(inView, (visible) => {
    if (visible) seen.value = true;
  });

  return { inView, seen };
}
