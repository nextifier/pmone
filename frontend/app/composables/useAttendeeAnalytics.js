import { useDocumentVisibility, useIntervalFn } from "@vueuse/core";

/**
 * Shared "attendees changed" signal for an event. Bumping it makes every live
 * analytics consumer (summary strip + detail dashboard) refetch immediately.
 * Same key across the app => same ref, so a mutation in the attendees table
 * instantly refreshes the Overview gauge without a page reload.
 */
export function useAttendeesChangedSignal(eventId) {
  return useState(`attendees-changed-${eventId}`, () => 0);
}

/**
 * Live attendee analytics feed. Fetches on mount, polls on an interval, pauses
 * while the tab is hidden, refetches on focus, and reacts to local mutations
 * via the shared changed-signal.
 *
 * @param {number|string} eventId
 * @param {"summary"|"detail"} variant
 * @param {{ interval?: number }} options
 */
export function useAttendeeAnalytics(eventId, variant = "summary", options = {}) {
  const interval = options.interval ?? 15000;
  const client = useSanctumClient();
  const data = ref(null);
  const pending = ref(true);
  const lastUpdatedAt = ref(null);

  const path =
    variant === "summary"
      ? `/api/events/${eventId}/attendees/analytics/summary`
      : `/api/events/${eventId}/attendees/analytics`;

  let inFlight = false;
  const refresh = async () => {
    if (!eventId || inFlight) {
      return;
    }
    inFlight = true;
    try {
      const res = await client(path);
      data.value = res?.data ?? null;
      lastUpdatedAt.value = Date.now();
    } catch {
      // Keep the last good snapshot on a transient error so the UI never blanks.
    } finally {
      inFlight = false;
      pending.value = false;
    }
  };

  const signal = useAttendeesChangedSignal(eventId);
  watch(signal, () => refresh());

  const visibility = useDocumentVisibility();
  const { pause, resume } = useIntervalFn(refresh, interval, { immediate: false });

  watch(visibility, (state) => {
    if (state === "visible") {
      refresh();
      resume();
    } else {
      pause();
    }
  });

  onMounted(() => {
    refresh();
    if (visibility.value !== "hidden") {
      resume();
    }
  });
  onBeforeUnmount(() => pause());

  return { data, pending, refresh, lastUpdatedAt };
}
