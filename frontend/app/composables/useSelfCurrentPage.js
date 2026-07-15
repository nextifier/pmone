/**
 * The current page shown for a user in presence UIs comes from the server, which
 * the presence heartbeat updates ~300ms after navigation. A view loaded right
 * after navigating therefore reads the server BEFORE the heartbeat lands, so the
 * logged-in user's own row/entry lags one navigation behind.
 *
 * The client is the authoritative source for its OWN location, so we override
 * the current user's entry with the live route + document title. Other users
 * still use the server value (best available, refreshed on reload / poll).
 */
export function useSelfCurrentPage() {
  const { user } = usePermission();
  const route = useRoute();

  const currentUserId = computed(() => user.value?.id ?? null);

  // titleTemplate is "%s · %siteName"; keep just the page part (mirrors the
  // heartbeat's own title extraction).
  const selfPage = () => ({
    path: route.path,
    title: (import.meta.client ? document.title.split("·")[0].trim() : "") || null,
  });

  return { currentUserId, selfPage };
}
