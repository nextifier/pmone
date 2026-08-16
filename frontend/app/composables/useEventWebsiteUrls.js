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

  const brandsPreviewUrl = computed(() =>
    base.value ? `${base.value}${editionPrefix.value}/brands?force-show-brands` : null
  );

  const rundownPreviewUrl = computed(() =>
    base.value ? `${base.value}${editionPrefix.value}/rundown?force-show-rundown` : null
  );

  const ticketsPreviewUrl = computed(() =>
    base.value && isActiveEdition.value ? `${base.value}/tickets?force-checkout-ticket` : null
  );

  return { base, isActiveEdition, brandsPreviewUrl, rundownPreviewUrl, ticketsPreviewUrl };
};
