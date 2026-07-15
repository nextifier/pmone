import { useDebounceFn, useDocumentVisibility, useIntervalFn } from "@vueuse/core";

/**
 * Fire-and-forget presence heartbeat. Reports the current SPA route + page title
 * to the API so admins can see who is online and on which page, and records a
 * page-view row on navigation for the user-activity analytics dashboard.
 *
 * Install once in the authenticated app layout. Runs client-side only; every
 * request is best-effort and silently swallows errors so presence never
 * disrupts the user.
 */
export function usePresenceHeartbeat() {
  if (import.meta.server) {
    return;
  }

  const { isAuthenticated } = useSanctumAuth();
  const client = useSanctumClient();
  const route = useRoute();

  // titleTemplate is "%s · %siteName"; keep just the page part.
  const pageTitle = () => {
    const raw = (document?.title || "").split("·")[0].trim();
    return raw || null;
  };

  let inFlight = false;
  const send = async (navigation) => {
    if (!isAuthenticated.value || inFlight) {
      return;
    }
    inFlight = true;
    try {
      await client("/api/presence/heartbeat", {
        method: "POST",
        body: { path: route.path, title: pageTitle(), navigation },
      });
    } catch {
      // Presence is best-effort; never surface a heartbeat error to the user.
    } finally {
      inFlight = false;
    }
  };

  const visibility = useDocumentVisibility();
  const { pause, resume } = useIntervalFn(() => send(false), 60000, { immediate: false });

  // Debounce route changes so a redirect chain reports only the final route.
  const onNavigate = useDebounceFn(() => send(true), 300);
  watch(() => route.path, () => onNavigate());

  watch(visibility, (state) => {
    if (state === "visible") {
      send(false);
      resume();
    } else {
      pause();
    }
  });

  // Cover login / logout without a full reload.
  watch(isAuthenticated, (authed) => {
    if (authed) {
      send(true);
      resume();
    } else {
      pause();
    }
  });

  onMounted(() => {
    send(true);
    if (visibility.value !== "hidden") {
      resume();
    }
  });
  onBeforeUnmount(() => pause());
}
