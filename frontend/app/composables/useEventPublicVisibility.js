import { toValue } from "vue";
import { toast } from "vue-sonner";

/**
 * One of the two per-event website visibility switches, wired to
 * `PUT /api/events/{id}/public-visibility`.
 *
 * Optimistic: the switch flips immediately and rolls back on failure, matching
 * the Business Matching toggle. The endpoint takes both flags with `sometimes`
 * rules, so sending only this one cannot clobber the other.
 *
 * @param {object|import('vue').Ref<object>} event Event payload (or a ref to one)
 * @param {'brands_public_visible'|'rundown_public_visible'} field
 * @param {string} noun Human name used in the success toast, e.g. "Brands"
 */
export const useEventPublicVisibility = (event, field, noun) => {
  const client = useSanctumClient();
  const refreshEvent = inject("refreshEvent", null);

  const pending = ref(false);
  const local = ref(Boolean(toValue(event)?.[field] ?? true));

  watch(
    () => toValue(event)?.[field],
    (value) => {
      if (value !== undefined && !pending.value) local.value = Boolean(value);
    },
    { immediate: true }
  );

  const toggle = async (next) => {
    const eventId = toValue(event)?.id;
    if (!eventId) return;

    const previous = local.value;
    local.value = next;
    pending.value = true;

    try {
      await client(`/api/events/${eventId}/public-visibility`, {
        method: "PUT",
        body: { [field]: next },
      });
      toast.success(next ? `${noun} are now visible on the website` : `${noun} are now hidden from the website`);
      await refreshEvent?.();
    } catch {
      local.value = previous;
      toast.error(`Failed to update ${noun.toLowerCase()} visibility`);
    } finally {
      pending.value = false;
    }
  };

  return { visible: local, pending, toggle };
};
