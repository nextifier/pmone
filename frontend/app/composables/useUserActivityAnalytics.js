import { useDocumentVisibility, useIntervalFn } from "@vueuse/core";

/**
 * Live user-activity analytics feed. Mirrors useAttendeeAnalytics: fetches on
 * mount, polls on an interval, pauses while the tab is hidden, refetches on
 * focus, and keeps the last good snapshot on a transient error so the UI never
 * blanks.
 *
 * @param {"summary"|"detail"} variant
 * @param {{ interval?: number }} options
 */
export function useUserActivityAnalytics(variant = "summary", options = {}) {
  const interval = options.interval ?? 20000;
  const client = useSanctumClient();
  const data = ref(null);
  const pending = ref(true);
  const lastUpdatedAt = ref(null);

  const path =
    variant === "summary"
      ? "/api/user-activity/analytics/summary"
      : "/api/user-activity/analytics";

  let inFlight = false;
  const refresh = async () => {
    if (inFlight) {
      return;
    }
    inFlight = true;
    try {
      const res = await client(path);
      data.value = res?.data ?? null;
      lastUpdatedAt.value = Date.now();
    } catch {
      // Keep the last good snapshot on a transient error.
    } finally {
      inFlight = false;
      pending.value = false;
    }
  };

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
