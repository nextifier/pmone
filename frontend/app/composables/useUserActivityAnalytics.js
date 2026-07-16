import { useDocumentVisibility, useIntervalFn } from "@vueuse/core";

/**
 * Live user-activity analytics feed. Mirrors useAttendeeAnalytics: fetches on
 * mount, polls on an interval, pauses while the tab is hidden, refetches on
 * focus, and keeps the last good snapshot on a transient error so the UI never
 * blanks.
 *
 * @param {"summary"|"detail"|"user"} variant
 * @param {{ interval?: number, username?: import("vue").MaybeRefOrGetter<string> }} options
 *   `username` is required by the "user" variant and may be a getter, so the
 *   feed follows a route param change.
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
  const path = computed(() => {
    if (variant === "user") {
      const username = toValue(options.username);
      return username ? `/api/user-activity/users/${username}/analytics` : null;
    }
    return variant === "summary"
      ? "/api/user-activity/analytics/summary"
      : "/api/user-activity/analytics";
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

  // Drop the previous user's snapshot right away: rendering their numbers under
  // someone else's name is wrong, not just stale.
  watch(path, () => {
    data.value = null;
    pending.value = true;
    lastUpdatedAt.value = null;
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
