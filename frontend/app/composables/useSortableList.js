import { useSortable } from "@vueuse/integrations/useSortable";

/**
 * Reusable sortable list composable.
 * Wraps VueUse's useSortable with common defaults and enabled/disabled toggle.
 * Must be called once during setup - handles element availability automatically.
 *
 * @param {Ref} elementRef - Ref to the sortable container DOM element (or computed)
 * @param {Ref<Array>} data - Ref to the array of items
 * @param {Object} options
 * @param {Function} [options.onReorder] - Callback after drag ends (async supported)
 * @param {Ref<boolean>} [options.enabled] - Optional condition ref (sortable disabled when false)
 * @param {Object} [options.sortableOptions] - Override default SortableJS options
 */
export function useSortableList(elementRef, data, options = {}) {
  const { onReorder, enabled, sortableOptions = {} } = options;

  const instance = useSortable(elementRef, data, {
    animation: 200,
    handle: ".drag-handle",
    ghostClass: "sortable-ghost",
    chosenClass: "sortable-chosen",
    dragClass: "sortable-drag",
    // Touch: require a short long-press before a drag starts, so a normal swipe
    // scrolls the page instead of accidentally reordering. Mouse stays instant
    // (delayOnTouchOnly); moving the finger >8px during the delay cancels the drag.
    delay: 200,
    delayOnTouchOnly: true,
    touchStartThreshold: 8,
    disabled: enabled ? !unref(enabled) : false,
    ...sortableOptions,
    onStart: (evt) => {
      if (typeof navigator !== "undefined" && navigator.vibrate) navigator.vibrate(15);
      if (sortableOptions.onStart) sortableOptions.onStart(evt);
    },
    onEnd: async (evt) => {
      await nextTick();
      if (onReorder) await onReorder();
      // Re-enable after DOM has fully settled (next animation frame)
      requestAnimationFrame(() => {
        if (sortableOptions.onEnd) sortableOptions.onEnd(evt);
      });
    },
  });

  // VueUse's tryOnMounted(start) only runs once at mount.
  // For elements set after mount (e.g. via querySelector in async onMounted),
  // we need our own watch to call start() when the element becomes available.
  watch(
    () => unref(elementRef),
    (el) => {
      if (el) instance.start();
    }
  );

  // Watch enabled condition - toggle disabled option on existing instance
  if (enabled !== undefined) {
    watch(
      () => unref(enabled),
      (isEnabled) => {
        instance.option("disabled", !isEnabled);
      }
    );
  }

  return instance;
}
