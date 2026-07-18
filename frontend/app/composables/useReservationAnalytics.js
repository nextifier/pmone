import { useDocumentVisibility, useIntervalFn } from "@vueuse/core";

/**
 * Shared "reservations changed" signal for an event. Bumping it makes every live
 * analytics consumer (summary strip + detail dashboard) refetch immediately.
 * Same key across the app => same ref, so a mutation in the reservations table
 * instantly refreshes the Overview gauge without a page reload.
 */
export function useReservationsChangedSignal(eventId) {
  return useState(`reservations-changed-${eventId}`, () => 0);
}

/**
 * Live reservation analytics feed. Fetches on mount, polls on an interval,
 * pauses while the tab is hidden, refetches on focus, and reacts to local
 * mutations via the shared changed-signal.
 *
 * @param {number|string} eventId
 * @param {"summary"|"detail"} variant
 * @param {{ interval?: number, range?: import("vue").MaybeRefOrGetter<{start: Date|null, end: Date|null}|null> }} options
 */
export function useReservationAnalytics(eventId, variant = "summary", options = {}) {
  const interval = options.interval ?? 15000;
  const client = useSanctumClient();
  const data = ref(null);
  const pending = ref(true);
  const lastUpdatedAt = ref(null);

  const basePath =
    variant === "summary"
      ? `/api/events/${eventId}/reservations/analytics/summary`
      : `/api/events/${eventId}/reservations/analytics`;

  // Only a complete range narrows the window; a half-picked range keeps the
  // current (all-time) payload on screen.
  const path = computed(() => {
    const range = toValue(options.range);
    if (range?.start && range?.end) {
      return `${basePath}?date_from=${toYmd(range.start)}&date_to=${toYmd(range.end)}`;
    }
    return basePath;
  });

  let inFlight = false;
  const refresh = async () => {
    if (!eventId || inFlight) {
      return;
    }
    inFlight = true;
    try {
      const res = await client(path.value);
      data.value = res?.data ?? null;
      lastUpdatedAt.value = Date.now();
    } catch {
      // Keep the last good snapshot on a transient error so the UI never blanks.
    } finally {
      inFlight = false;
      pending.value = false;
    }
  };

  const signal = useReservationsChangedSignal(eventId);
  watch(signal, () => refresh());
  watch(path, () => refresh());

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
