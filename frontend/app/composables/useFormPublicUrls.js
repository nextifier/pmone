import { toValue } from "vue";

/**
 * The two places a public form can be opened. A form that belongs to a project
 * is served both from pmone.id and from that project's event website, and the
 * dashboard offers whichever the visitor is meant to receive.
 *
 * @param {object|import('vue').Ref<object>} form Form payload (or a ref to one)
 * @returns {{pmoneUrl: import('vue').ComputedRef<string>, eventUrl: import('vue').ComputedRef<string|null>}}
 *   `eventUrl` is null when the form has no project, or when that project has no
 *   "Website" link configured - both are ordinary states, not errors.
 */
export const useFormPublicUrls = (form) => {
  const config = useRuntimeConfig();

  const slug = computed(() => toValue(form)?.slug ?? "");

  const pmoneUrl = computed(() => `${config.public.siteUrl}/f/${slug.value}`);

  const eventUrl = computed(() => {
    const website = toValue(form)?.project?.website_url;
    if (!website || !slug.value) return null;

    return `${website.replace(/\/+$/, "")}/f/${slug.value}`;
  });

  return { pmoneUrl, eventUrl };
};
