import { computed, ref, unref } from "vue";
import { useDebounceFn } from "@vueuse/core";

/**
 * Non-blocking, debounced, race-safe persistence for a drag-reorderable list.
 *
 * The list is reordered optimistically (SortableJS mutates `items` in place);
 * this composable only persists the new order in the background, so dragging is
 * never blocked by an in-flight request.
 *
 * - Debounce: a burst of drops collapses into ONE request with the final order.
 * - Single-flight: only one request runs at a time. If the order changes while a
 *   request is in flight, the latest order is queued and fired when it returns,
 *   so the last write always wins and responses can never apply out of order.
 * - Revert: on failure the list snaps back to the last server-confirmed order.
 *
 * @param {object} options
 * @param {import('vue').Ref<Array>} options.items   The local working copy (mutated by SortableJS).
 * @param {Function} options.client                  Sanctum/ofetch client.
 * @param {string|Function|import('vue').Ref<string>} [options.endpoint]
 * @param {string|import('vue').Ref<string>} [options.idKey]
 * @param {number} [options.debounceMs]
 * @param {(orderedItems: Array) => void} [options.onCommit] Push the confirmed order to the model.
 * @param {() => void} [options.onChanged]
 * @param {(error: unknown) => void} [options.onError]
 */
export function useGalleryReorder(options) {
  const {
    items,
    client,
    endpoint = "/api/media/reorder",
    idKey = "id",
    debounceMs = 500,
    onCommit,
    onChanged,
    onError,
  } = options;

  const getId = (media) => media[unref(idKey)];
  const resolveEndpoint = () => (typeof endpoint === "function" ? endpoint() : unref(endpoint));

  const saveStatus = ref("idle"); // 'idle' | 'saving' | 'saved' | 'error'
  const lastSavedItems = ref([]);
  const lastSavedKey = computed(() => lastSavedItems.value.map(getId).join(","));

  let inFlight = false;
  let pendingOrder = null;
  let savedTimer = null;

  function seedSaved(arr) {
    lastSavedItems.value = (Array.isArray(arr) ? arr : []).map((media) => ({ ...media }));
  }

  const scheduleSave = useDebounceFn(() => {
    if (items.value.length < 2) {
      return;
    }
    const order = items.value.map(getId);
    if (order.join(",") === lastSavedKey.value) {
      return;
    }
    if (inFlight) {
      pendingOrder = order;
      return;
    }
    runSave(order);
  }, debounceMs);

  async function runSave(order) {
    inFlight = true;
    pendingOrder = null;
    saveStatus.value = "saving";

    const byId = new Map(items.value.map((media) => [getId(media), media]));
    const snapshot = order.map((id) => byId.get(id)).filter(Boolean);

    try {
      await client(resolveEndpoint(), { method: "POST", body: { media_ids: order } });
      lastSavedItems.value = snapshot.map((media) => ({ ...media }));
      onCommit?.(snapshot);
      onChanged?.();
      flashSaved();
    } catch (error) {
      pendingOrder = null; // we are reverting; drop any queued save
      items.value = lastSavedItems.value.map((media) => ({ ...media }));
      onCommit?.(items.value);
      saveStatus.value = "error";
      onError?.(error);
      onChanged?.();
    } finally {
      inFlight = false;
      if (pendingOrder) {
        const next = pendingOrder;
        pendingOrder = null;
        runSave(next);
      }
    }
  }

  function flashSaved() {
    saveStatus.value = "saved";
    clearTimeout(savedTimer);
    savedTimer = setTimeout(() => {
      if (saveStatus.value === "saved") {
        saveStatus.value = "idle";
      }
    }, 1500);
  }

  return { saveStatus, lastSavedItems, lastSavedKey, scheduleSave, seedSaved };
}
