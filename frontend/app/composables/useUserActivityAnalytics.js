import { useDocumentVisibility, useIntervalFn } from "@vueuse/core";

/**
 * Live user-activity analytics feed. Mirrors useAttendeeAnalytics: fetches on
 * mount, polls on an interval, pauses while the tab is hidden, refetches on
 * focus, and keeps the last good snapshot on a transient error so the UI never
 * blanks.
 *
 * @param {"summary"|"detail"|"user"} variant
 * @param {{ interval?: number, username?: import("vue").MaybeRefOrGetter<string>, range?: import("vue").MaybeRefOrGetter<{start: Date|null, end: Date|null}|null> }} options
 *   `username` is required by the "user" variant and may be a getter, so the
 *   feed follows a route param change. A complete `range` narrows the window
 *   via date_from/date_to; absent, the backend's 30-day default applies.
 */
export function useUserActivityAnalytics(variant = "summary", options = {}) {
  const interval = options.interval ?? 20000;
  const client = useSanctumClient();
  const data = ref(null);
  const pending = ref(true);
  const lastUpdatedAt = ref(null);

  // Reactive so the "user" variant follows its param: vue-router reuses the
  // component instance between /users/a/activity and /users/b/activity, so a
  // fixed path would keep polling the first user under the second one's header.
  const basePath = computed(() => {
    if (variant === "user") {
      const username = toValue(options.username);
      return username ? `/api/user-activity/users/${username}/analytics` : null;
    }
    return variant === "summary"
      ? "/api/user-activity/analytics/summary"
      : "/api/user-activity/analytics";
  });

  // Only a complete range narrows the window; a half-picked range keeps the
  // current payload on screen.
  const path = computed(() => {
    if (!basePath.value) {
      return null;
    }
    const range = toValue(options.range);
    if (range?.start && range?.end) {
      return `${basePath.value}?date_from=${toYmd(range.start)}&date_to=${toYmd(range.end)}`;
    }
    return basePath.value;
  });

  let inFlight = false;
  const refresh = async () => {
    if (inFlight || !path.value) {
      return;
    }
    inFlight = true;
    const requested = path.value;
    try {
      const res = await client(requested);
      // The param may have changed mid-flight; never let one user's response
      // land in another's view.
      if (requested !== path.value) {
        return;
      }
      data.value = res?.data ?? null;
      lastUpdatedAt.value = Date.now();
    } catch {
      // Keep the last good snapshot on a transient error.
    } finally {
      inFlight = false;
      pending.value = false;
    }
  };

  // Drop the previous user's snapshot right away when the SUBJECT changes:
  // rendering their numbers under someone else's name is wrong, not just
  // stale. A range change on the same subject keeps the current data on
  // screen while the narrowed payload loads.
  watch(path, (next, prev) => {
    if (next?.split("?")[0] !== prev?.split("?")[0]) {
      data.value = null;
      pending.value = true;
      lastUpdatedAt.value = null;
    }
    refresh();
  });

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
