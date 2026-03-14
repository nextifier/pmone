import { useSortable } from "@vueuse/integrations/useSortable";

/**
 * Reusable sortable list with proper instance lifecycle management.
 * Follows the working pattern from projects/index.vue - properly destroys
 * old instances before creating new ones (fixes mobile touch events).
 *
 * @param {Ref} elementRef - Ref to the sortable container DOM element (or computed)
 * @param {Ref<Array>} data - Ref to the array of items
 * @param {Object} options
 * @param {Function} [options.onReorder] - Callback after drag ends (async supported)
 * @param {Ref<boolean>} [options.enabled] - Optional condition ref (sortable disabled when false)
 * @param {Object} [options.sortableOptions] - Override default SortableJS options
 * @returns {{ initialize: Function }}
 */
export function useSortableList(elementRef, data, options = {}) {
  const { onReorder, enabled, sortableOptions = {} } = options;

  let instance = null;

  const initialize = () => {
    // Destroy existing instance to prevent duplicate event handlers
    if (instance?.stop) {
      instance.stop();
      instance = null;
    }

    // Check enabled condition if provided
    if (enabled && !unref(enabled)) return;

    // Must have data
    if (!data.value?.length) return;

    nextTick(() => {
      const el = unref(elementRef);
      if (!el) return;

      instance = useSortable(el, data, {
        animation: 200,
        handle: ".drag-handle",
        ghostClass: "sortable-ghost",
        chosenClass: "sortable-chosen",
        dragClass: "sortable-drag",
        ...sortableOptions,
        onEnd: async () => {
          await nextTick();
          if (onReorder) await onReorder();
        },
      });
    });
  };

  // Auto-reinitialize when data length changes (add/remove/refetch)
  watch(() => data.value?.length, () => initialize());

  // Watch enabled condition if provided
  if (enabled !== undefined) {
    watch(() => unref(enabled), () => initialize());
  }

  return { initialize };
}
