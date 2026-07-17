import { useDocumentVisibility, useOnline } from "@vueuse/core";
import type { Ref } from "vue";

export type PollingFetcher = () => unknown | Promise<unknown>;

export type PollingMode = "cancel" | "rest" | "overlap";

export interface PollingOptions {
  /** Start automatically on mount. Default: true. */
  autoStart?: boolean;
  /**
   * What happens when a tick fires while the previous request is still running.
   * Default: "rest".
   * - "rest":    the interval is measured from the END of the previous request
   *              to the start of the next one (never overlaps).
   * - "cancel":  fixed cadence; a tick is skipped if a request is in flight.
   * - "overlap": fixed cadence; always fires, even with a request in flight.
   */
  mode?: PollingMode;
  /** Keep polling while the tab is hidden. Default: false (pause when hidden,
   *  refresh immediately and resume the interval when visible again). */
  keepAlive?: boolean;
  /** Fire the first tick right away on start instead of waiting one interval.
   *  Use when the fetcher is also responsible for the initial load (no
   *  useFetch doing it on mount). Default: false. */
  immediate?: boolean;
  /** Slow down while the fetcher keeps failing: the delay doubles per
   *  consecutive rejection (capped at 5x intervalMs) and resets on success.
   *  Only rejections count — a fetcher that swallows its own errors polls at
   *  the normal cadence. Default: true. */
  backoff?: boolean;
}

export interface UsePollingReturn {
  start: () => void;
  stop: () => void;
  /** Intent flag: true from start() until stop()/unmount — stays true while
   *  paused because the tab is hidden or the browser is offline. Not an
   *  in-flight indicator. */
  isPolling: Readonly<Ref<boolean>>;
}

/**
 * Reusable interval refetch (stands in for Inertia's `usePoll`).
 * Starts on mount, stops on unmount. By default the first tick waits one full
 * interval — the initial data is assumed to be fetched by
 * useFetch/useLazySanctumFetch — pass `immediate: true` when the fetcher owns
 * the initial load too. Always pauses while the browser is offline and
 * refetches as soon as the connection returns. A fetcher that throws does not
 * kill the loop.
 *
 * @example
 * const { data, refresh } = await useLazySanctumFetch("/api/users");
 * usePolling(refresh, 20000); // refetch every 20s, paused while the tab is hidden
 *
 * @example
 * // Manual fetcher that also does the initial load:
 * usePolling(loadStats, 20000, { immediate: true });
 *
 * @example
 * const { start, stop, isPolling } = usePolling(refresh, 10000, {
 *   autoStart: false,
 *   mode: "cancel",
 *   keepAlive: true,
 * });
 */
export function usePolling(
  fetcher: PollingFetcher,
  intervalMs = 15000,
  options: PollingOptions = {}
): UsePollingReturn {
  const {
    autoStart = true,
    mode = "rest",
    keepAlive = false,
    immediate = false,
    backoff = true,
  } = options;

  const active = ref(false);
  let timer: ReturnType<typeof setTimeout> | null = null;
  let inFlight = false;
  // Generation counter: stop() bumps it so continuations from an in-flight
  // fetch (or a queued timer callback) can no longer reschedule the loop.
  let runId = 0;
  let mounted = false;
  let failures = 0;

  const visibility = keepAlive ? null : useDocumentVisibility();
  const isHidden = () => visibility !== null && visibility.value === "hidden";
  const online = useOnline();

  const currentDelay = () =>
    backoff && failures > 0 ? Math.min(intervalMs * 2 ** failures, intervalMs * 5) : intervalMs;

  const clearTimer = () => {
    if (timer !== null) {
      clearTimeout(timer);
      timer = null;
    }
  };

  const scheduleNext = (id: number) => {
    if (id !== runId || !active.value || isHidden() || !online.value) {
      return;
    }
    clearTimer();
    timer = setTimeout(() => {
      timer = null;
      tick(id);
    }, currentDelay());
  };

  const execute = async (id: number) => {
    inFlight = true;
    try {
      await fetcher();
      failures = 0;
    } catch {
      // A failing fetcher must not kill the polling loop; it only backs off.
      failures += 1;
    } finally {
      inFlight = false;
      if (mode === "rest") {
        scheduleNext(id);
      }
    }
  };

  const tick = (id: number) => {
    if (id !== runId || !active.value) {
      return;
    }
    if (mode === "rest") {
      // Never overlap: if a request is still running (e.g. an immediate tick
      // fired on tab focus), retry on the next interval instead.
      if (inFlight) {
        scheduleNext(id);
        return;
      }
      void execute(id);
      return;
    }
    scheduleNext(id);
    if (mode === "cancel" && inFlight) {
      return;
    }
    void execute(id);
  };

  const launch = (id: number) => {
    if (immediate) {
      tick(id);
    } else {
      scheduleNext(id);
    }
  };

  const start = () => {
    if (active.value) {
      return;
    }
    active.value = true;
    // Before mount (or on the server) only record intent; onMounted launches.
    if (mounted) {
      launch(runId);
    }
  };

  const stop = () => {
    active.value = false;
    runId += 1;
    clearTimer();
  };

  if (visibility) {
    watch(visibility, (state) => {
      if (!active.value) {
        return;
      }
      clearTimer();
      if (state === "visible" && online.value) {
        tick(runId); // refresh immediately on focus, then resume the interval
      }
    });
  }

  watch(online, (isOnline) => {
    if (!active.value) {
      return;
    }
    clearTimer();
    if (isOnline && !isHidden()) {
      tick(runId); // the data went stale while offline — refetch right away
    }
  });

  onMounted(() => {
    mounted = true;
    if (autoStart && !active.value) {
      active.value = true;
    }
    if (active.value) {
      launch(runId);
    }
  });

  onBeforeUnmount(stop);

  return { start, stop, isPolling: readonly(active) };
}
