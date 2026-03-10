/**
 * Composable for header project/event switcher.
 * Fetches navigation data once and caches it in useState.
 */
export function useHeaderNavigation() {
  const navigation = useState<any[]>("header-navigation", () => []);
  const loaded = useState("header-navigation-loaded", () => false);

  const fetchNavigation = async () => {
    if (loaded.value) return;

    try {
      const client = useSanctumClient();
      const response = await client("/api/dashboard/navigation");
      navigation.value = response?.data || [];
      loaded.value = true;
    } catch {
      // Silently fail - navigation is not critical
    }
  };

  return {
    navigation,
    loaded,
    fetchNavigation,
  };
}
