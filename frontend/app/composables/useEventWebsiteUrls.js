import { toValue } from "vue";

/**
 * Deep links into an event's public website, each carrying the admin bypass
 * parameter that reveals content the public cannot see yet.
 *
 * Every URL is null when the event's project has no "Website" link configured -
 * an ordinary state, not an error - so call sites should hide the button rather
 * than render a dead one. Mirrors `useFormPublicUrls`.
 *
 * Past editions live under `/{edition_number}/...` on the event websites, but
 * only for brands and rundown: there is no per-edition tickets page, so the
 * checkout preview is offered for the active edition only.
 *
 * @param {object|import('vue').Ref<object>} event Event payload (or a ref to one)
 */
export const useEventWebsiteUrls = (event) => {
  const base = computed(() => {
    const url = toValue(event)?.website_url;
    return url ? String(url).replace(/\/+$/, "") : null;
  });

  const isActiveEdition = computed(() => Boolean(toValue(event)?.is_active));

  const editionPrefix = computed(() => {
    if (isActiveEdition.value) return "";
    const edition = toValue(event)?.edition_number;
    return edition ? `/${edition}` : "";
  });

  // `=1` rather than the bare param: an explicit value survives every URL
  // normaliser between here and the page (chat clients, redirects, analytics
  // rewrites), any of which can drop a valueless key.
  const brandsPreviewUrl = computed(() =>
    base.value ? `${base.value}${editionPrefix.value}/brands?force-show-brands=1` : null
  );

  const rundownPreviewUrl = computed(() =>
    base.value ? `${base.value}${editionPrefix.value}/rundown?force-show-rundown=1` : null
  );

  const ticketsPreviewUrl = computed(() =>
    base.value && isActiveEdition.value ? `${base.value}/tickets?force-checkout-ticket=1` : null
  );

  return { base, isActiveEdition, brandsPreviewUrl, rundownPreviewUrl, ticketsPreviewUrl };
};
