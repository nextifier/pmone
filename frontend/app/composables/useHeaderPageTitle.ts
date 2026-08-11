import { onScopeDispose, watch, type MaybeRefOrGetter } from "vue";

/**
 * Publishes the current record's name to the app header for pages outside the
 * project hierarchy (which has its own HeaderBreadcrumb).
 *
 * Only the name travels through state. Where the back button goes and what the
 * section is called are static per route and live in
 * `definePageMeta({ headerContext })`, because AppHeader renders BEFORE the page
 * during SSR: anything the page sets in `setup` is still empty at that point,
 * and the header would render one shape on the server and another on the
 * client. Route meta is resolved before either.
 *
 * The name itself is only known after a fetch, so HeaderPageContext renders it
 * inside `<ClientOnly>` — the same split HeaderBreadcrumb already uses for the
 * project/event chain.
 */
export function useHeaderPageTitle(title: MaybeRefOrGetter<string | null | undefined>) {
  const state = useState<string | null>("header-page-title", () => null);

  watch(
    () => toValue(title),
    (value) => {
      state.value = value || null;
    },
    { immediate: true },
  );

  onScopeDispose(() => {
    state.value = null;
  });
}
