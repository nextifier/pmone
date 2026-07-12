<template>
  <div class="flex flex-col gap-y-6">
    <div class="space-y-1">
      <h2 class="page-title">Website Settings</h2>
      <p class="page-description">
        Control how the public website renders sections sourced from this project. Changes are saved
        automatically.
      </p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <Spinner class="size-5" />
    </div>

    <div v-else class="flex flex-col gap-y-4">
      <!-- Home page sections -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:home-01" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">Home Page</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Choose which sections appear on the public home page. The list is specific to this
              event.
            </p>
          </div>
        </div>

        <div class="frame-panel divide-border divide-y !px-0 !py-0">
          <div
            v-for="section in homeSectionsCatalog"
            :key="section.key"
            class="flex items-center justify-between gap-4 px-4 py-5 lg:px-6"
          >
            <Label
              :for="`home-${section.key}`"
              class="cursor-pointer text-sm font-medium tracking-tight"
            >
              {{ section.label }}
            </Label>
            <Switch :id="`home-${section.key}`" v-model="form.home_sections[section.key]" />
          </div>
        </div>
      </div>

      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:time-schedule" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">Rundown</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Configure how the Rundown section behaves on the public event website.
            </p>
          </div>
        </div>

        <div class="frame-panel divide-border divide-y !px-0 !py-0">
          <div class="flex items-start justify-between gap-4 px-4 py-5 lg:px-6">
            <div class="space-y-1">
              <Label
                for="rundown-show-search"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Show search bar on Rundown section
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                Visitors can filter rundown items by typing a keyword.
              </p>
            </div>
            <Switch id="rundown-show-search" v-model="form.show_search_bar" />
          </div>

          <div class="flex items-start justify-between gap-4 px-4 py-5 lg:px-6">
            <div class="space-y-1">
              <Label
                for="rundown-show-location-filter"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Show location filter on Rundown section
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                Visitors can filter rundown items by venue or room.
              </p>
            </div>
            <Switch id="rundown-show-location-filter" v-model="form.show_location_filter" />
          </div>

          <div class="flex items-start justify-between gap-4 px-4 py-5 lg:px-6">
            <div class="space-y-1">
              <Label
                for="rundown-show-all-details"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Show all rundown details without click
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                Render description, speakers, and panelists inline instead of behind a click.
              </p>
            </div>
            <Switch id="rundown-show-all-details" v-model="form.show_all_rundown_details" />
          </div>
        </div>
      </div>

      <!-- Ticket page tabs -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:ticket-01" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">Ticket Page Tabs</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Choose which tabs appear on the public ticket page.
            </p>
          </div>
        </div>

        <div class="frame-panel divide-border divide-y !px-0 !py-0">
          <div
            v-for="tab in ticketTabFields"
            :key="tab.key"
            class="flex items-start justify-between gap-4 px-4 py-5 lg:px-6"
          >
            <Label
              :for="`ticket-${tab.key}`"
              class="cursor-pointer text-sm font-medium tracking-tight"
            >
              {{ tab.label }}
            </Label>
            <Switch :id="`ticket-${tab.key}`" v-model="form.ticket_tabs[tab.key]" />
          </div>
        </div>
      </div>

      <!-- Blog -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:news-01" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">Blog</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Control what shows on blog post cards.
            </p>
          </div>
        </div>

        <div class="frame-panel divide-border divide-y !px-0 !py-0">
          <div class="flex items-start justify-between gap-4 px-4 py-5 lg:px-6">
            <div class="space-y-1">
              <Label
                for="blog-show-author"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Show author on post cards
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                Display the post author next to each blog post card.
              </p>
            </div>
            <Switch id="blog-show-author" v-model="form.blog.show_post_card_author" />
          </div>

          <div class="flex items-start justify-between gap-4 px-4 py-5 lg:px-6">
            <div class="space-y-1">
              <Label
                for="blog-show-excerpt"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Show excerpt on post cards
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                Display a short excerpt under each blog post card title.
              </p>
            </div>
            <Switch id="blog-show-excerpt" v-model="form.blog.show_post_card_excerpt" />
          </div>
        </div>
      </div>

      <!-- Book Space form -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:building-03" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">Book Space Form</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Choose which optional fields appear on the exhibitor "Book Space" form.
            </p>
          </div>
        </div>

        <div class="frame-panel divide-border divide-y !px-0 !py-0">
          <div
            v-for="field in bookSpaceFields"
            :key="field.key"
            class="flex items-start justify-between gap-4 px-4 py-5 lg:px-6"
          >
            <Label
              :for="`book-${field.key}`"
              class="cursor-pointer text-sm font-medium tracking-tight"
            >
              {{ field.label }}
            </Label>
            <Switch :id="`book-${field.key}`" v-model="form.book_space_form[field.key]" />
          </div>
        </div>
      </div>

      <!-- Terms -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:legal-document-01" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">Terms &amp; Privacy</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              The "Last updated" date shown on the Terms and Privacy pages.
            </p>
          </div>
        </div>

        <div class="frame-panel !px-0 !py-0">
          <div class="flex flex-col gap-2 px-4 py-5 lg:px-6">
            <Label class="text-sm font-medium tracking-tight">Last updated</Label>
            <DatePicker
              v-model="form.terms_last_update"
              placeholder="Select a date"
              class="max-w-xs"
            />
          </div>
        </div>
      </div>

      <!-- Data Fallback -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:database-restore" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">Data Fallback</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              When the active event has no data for a section yet, borrow content from the most
              recent previous edition that does. Turn a section off to show only the active event's
              own data, so empty sections stay empty.
            </p>
          </div>
        </div>

        <div class="frame-panel divide-border divide-y !px-0 !py-0">
          <div
            v-for="field in dataFallbackFields"
            :key="field.key"
            class="flex items-start justify-between gap-4 px-4 py-5 lg:px-6"
          >
            <div class="space-y-1">
              <Label
                :for="`fallback-${field.key}`"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                {{ field.label }}
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">{{ field.description }}</p>
            </div>
            <Switch :id="`fallback-${field.key}`" v-model="form.data_fallback[field.key]" />
          </div>
        </div>
      </div>

      <!-- Navigation -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:menu-square" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">Navigation</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Manage the header, mobile menu, and footer links on the public website. Changes apply
              without a site rebuild. Leave a list empty to keep the site's built-in navigation.
            </p>
          </div>
        </div>

        <div class="frame-panel space-y-6 !px-4 !py-5 lg:!px-6">
          <NavigationListEditor
            v-model="form.site_config.nav.header"
            title="Header"
            description="Links shown in the main site header."
          />
          <div class="border-t pt-6">
            <NavigationListEditor
              v-model="form.site_config.nav.dialog"
              title="Mobile menu"
              description="Links shown in the mobile navigation dialog."
            />
          </div>
          <div class="border-t pt-6">
            <NavigationListEditor
              v-model="form.site_config.nav.footer"
              title="Footer"
              description="Link groups shown in the site footer."
            />
          </div>
        </div>
      </div>

      <!-- Analytics -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:analytics-01" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">Analytics</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Set the tracking ids used on the public website. Changes apply without a site
              rebuild. Leave a field blank to keep the site's built-in id.
            </p>
          </div>
        </div>

        <div class="frame-panel space-y-4 !px-4 !py-5 lg:!px-6">
          <div class="space-y-2">
            <Label for="analytics-ga4" class="text-sm font-medium tracking-tight">
              GA4 Measurement ID
            </Label>
            <Input
              id="analytics-ga4"
              v-model="form.site_config.analytics.ga4"
              placeholder="G-XXXXXXXXXX"
            />
            <FieldError :errors="errors['site_config.analytics.ga4']" />
          </div>

          <div class="space-y-2">
            <Label for="analytics-tiktok-pixel" class="text-sm font-medium tracking-tight">
              TikTok Pixel ID
            </Label>
            <Input
              id="analytics-tiktok-pixel"
              v-model="form.site_config.analytics.tiktok_pixel"
              placeholder="CXXXXXXXXXXXXXXXXXXX"
            />
            <FieldError :errors="errors['site_config.analytics.tiktok_pixel']" />
          </div>
        </div>
      </div>

      <!-- Appearance -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:paint-brush-01" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">Appearance</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Retheme the public website with a curated color palette. Changes apply without a
              site rebuild. Leave this off to keep the site's built-in palette.
            </p>
          </div>
        </div>

        <div class="frame-panel space-y-4 !px-4 !py-5 lg:!px-6">
          <div class="flex items-start justify-between gap-4">
            <div class="space-y-1">
              <Label
                for="appearance-enabled"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Enable dashboard palette
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                Overrides the site's baked-in palette with the colors below.
              </p>
            </div>
            <Switch id="appearance-enabled" v-model="form.site_config.appearance.enabled" />
          </div>

          <div
            v-if="form.site_config.appearance.enabled"
            class="grid grid-cols-1 gap-3 border-t pt-4 sm:grid-cols-2"
          >
            <AppearancePicker
              label="Base Color"
              variant="swatch"
              fluid
              :model-value="form.site_config.appearance.baseColor"
              :options="BASE_COLOR_OPTIONS"
              @update:model-value="(v) => (form.site_config.appearance.baseColor = v)"
            />
            <AppearancePicker
              label="Theme"
              variant="swatch"
              fluid
              :model-value="form.site_config.appearance.theme"
              :options="THEME_OPTIONS"
              @update:model-value="(v) => (form.site_config.appearance.theme = v)"
            />
            <AppearancePicker
              label="Chart Color"
              variant="swatch"
              fluid
              :model-value="form.site_config.appearance.chartColor"
              :options="CHART_COLOR_OPTIONS"
              @update:model-value="(v) => (form.site_config.appearance.chartColor = v)"
            />
            <AppearancePicker
              label="Radius"
              variant="radius"
              fluid
              :model-value="form.site_config.appearance.radius"
              :options="radiusOptions"
              @update:model-value="(v) => (form.site_config.appearance.radius = v)"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import AppearancePicker from "@/components/appearance/AppearancePicker.vue";
import NavigationListEditor from "@/components/project/NavigationListEditor.vue";
import { FieldError } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { BASE_COLOR_OPTIONS, CHART_COLOR_OPTIONS, RADII, THEME_OPTIONS } from "@/lib/appearance";
import { toast } from "vue-sonner";

const props = defineProps({
  project: Object,
});

usePageMeta(null, {
  title: computed(() => `Website Settings · ${props.project?.name || ""}`),
});

const route = useRoute();
const client = useSanctumClient();

const loading = ref(true);

// Home page section toggles are data-driven: the catalog (labels + order) comes
// from the backend config/home_sections.php and is specific to this project.
const homeSectionsCatalog = ref([]);

const ticketTabFields = [
  { key: "show_tickets", label: "Tickets" },
  { key: "show_guests", label: "Guests" },
  { key: "show_brands", label: "Brands" },
  { key: "show_rundown", label: "Rundown" },
  { key: "show_about", label: "About" },
  { key: "show_photos", label: "Photos" },
];

const bookSpaceFields = [
  { key: "show_job_title", label: "Job title field" },
  { key: "show_brand_name", label: "Brand name field" },
  { key: "show_products", label: "Products field" },
];

// Curated shadcn radii, mapped to the AppearancePicker option shape (mirrors
// AppearanceCustomizer.vue's radiusOptions).
const radiusOptions = RADII.map((r) => ({ value: r.name, label: r.title }));

const dataFallbackFields = [
  {
    key: "brands",
    label: "Brands",
    description: "Borrow exhibitors/brands (incl. the home Brand Preview) from a previous edition.",
  },
  {
    key: "guests",
    label: "Guests",
    description: "Borrow speakers/guests from a previous edition.",
  },
  {
    key: "partners",
    label: "Partners",
    description: "Borrow partners (Credits) from a previous edition.",
  },
  {
    key: "programs",
    label: "Programs",
    description: "Borrow programs from a previous edition.",
  },
  {
    key: "faqs",
    label: "FAQ",
    description: "Borrow FAQ entries from a previous edition.",
  },
  {
    key: "gallery",
    label: "Photos (Gallery)",
    description: "Borrow gallery photos from a previous edition.",
  },
  {
    key: "media_coverages",
    label: "Media Coverage",
    description: "Borrow media coverage items from a previous edition.",
  },
];

const ticketTabDefaults = () => ({
  show_tickets: true,
  show_guests: false,
  show_brands: true,
  show_rundown: true,
  show_about: true,
  show_photos: true,
});

const bookSpaceDefaults = () => ({
  show_job_title: false,
  show_brand_name: true,
  show_products: false,
});

const dataFallbackDefaults = () => ({
  brands: true,
  guests: true,
  partners: true,
  programs: true,
  faqs: true,
  gallery: true,
  media_coverages: true,
});

const navDefaults = () => ({ header: [], dialog: [], footer: [] });
const analyticsDefaults = () => ({ ga4: null, tiktok_pixel: null });
// Sane starting selections for the pickers when a project has never saved a
// palette (mirrors DEFAULT_APPEARANCE's baseColor/theme/chartColor/radius —
// `style`/`font`/`fontHeading` are the separate `useAppearance` concern, out
// of scope for this dashboard-managed block).
const appearanceDefaults = () => ({
  enabled: false,
  baseColor: "neutral",
  theme: "neutral",
  chartColor: "neutral",
  radius: "default",
});

const form = ref({
  show_search_bar: true,
  show_location_filter: true,
  show_all_rundown_details: false,
  home_sections: {},
  blog: { show_post_card_author: false, show_post_card_excerpt: false },
  ticket_tabs: ticketTabDefaults(),
  book_space_form: bookSpaceDefaults(),
  terms_last_update: null,
  data_fallback: dataFallbackDefaults(),
  site_config: { nav: navDefaults(), analytics: analyticsDefaults(), appearance: appearanceDefaults() },
});

// Field-level validation errors from the last failed save, keyed by the
// backend's dot-notation field name (e.g. "site_config.analytics.ga4").
const errors = ref({});

let navKeySeed = 0;
const withNavKey = (item) => ({ ...item, _key: `nav-${Date.now()}-${navKeySeed++}` });

// NavigationListEditor needs a stable client-only `_key` per item to track
// drag reorder / edit / delete. Strip it back out before saving so the stored
// JSON matches the backend's { label, path } / { label, links } shape exactly.
function stripNavKeys(items) {
  return (items ?? []).map(({ _key, links, ...rest }) => ({
    ...rest,
    ...(Array.isArray(links) ? { links: links.map(({ _key: lk, ...link }) => link) } : {}),
  }));
}

function hydrateNav(nav) {
  const source = nav && typeof nav === "object" ? nav : {};
  const toList = (list) =>
    Array.isArray(list)
      ? list.map((item) =>
          withNavKey(
            Array.isArray(item.links)
              ? { label: item.label, links: item.links.map((l) => withNavKey(l)) }
              : { label: item.label, path: item.path }
          )
        )
      : [];

  return {
    header: toList(source.header),
    dialog: toList(source.dialog),
    footer: toList(source.footer),
  };
}

// Format a Date to a plain "YYYY-MM-DD" using local parts (no TZ shift).
function toIsoDate(date) {
  if (!date) return null;
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

// An emptied text input becomes "" via v-model, but the backend's
// nullable|regex rules only accept null or a matching string - never send an
// empty string through.
function blankToNull(value) {
  const trimmed = (value ?? "").trim();
  return trimmed === "" ? null : trimmed;
}

// Snapshot of the last persisted payload. Auto-save no-ops when nothing
// changed — e.g. focusing then blurring a field without editing it.
let lastSavedSnapshot = null;
let saving = false;
let savePending = false;

function buildPayload() {
  return {
    rundown: {
      show_search_bar: form.value.show_search_bar,
      show_location_filter: form.value.show_location_filter,
      show_all_rundown_details: form.value.show_all_rundown_details,
    },
    home_sections: { ...form.value.home_sections },
    blog: { ...form.value.blog },
    ticket_tabs: { ...form.value.ticket_tabs },
    book_space_form: { ...form.value.book_space_form },
    terms: { last_update: toIsoDate(form.value.terms_last_update) },
    data_fallback: { ...form.value.data_fallback },
    // Wholesale-replace on the backend (ProjectController::updateWebsiteSettings)
    // - always send all three lists together so an untouched list is not lost.
    site_config: {
      nav: {
        header: stripNavKeys(form.value.site_config.nav.header),
        dialog: stripNavKeys(form.value.site_config.nav.dialog),
        footer: stripNavKeys(form.value.site_config.nav.footer),
      },
      analytics: {
        ga4: blankToNull(form.value.site_config.analytics.ga4),
        tiktok_pixel: blankToNull(form.value.site_config.analytics.tiktok_pixel),
      },
      appearance: { ...form.value.site_config.appearance },
    },
  };
}

async function load() {
  loading.value = true;
  try {
    const response = await client(`/api/projects/${route.params.username}`);
    homeSectionsCatalog.value = response.data?.home_sections_catalog ?? [];
    // Resolved current values (stored -> legacy -> default) from the backend, so
    // the switches reflect real state on first load.
    const resolvedHomeSections = response.data?.home_sections ?? {};
    const settings = response.data?.settings ?? {};
    const ws = settings.website_settings ?? {};
    const rundown = ws.rundown ?? {};
    const blog = ws.blog ?? {};
    const ticketTabs = ws.ticket_tabs ?? {};
    const bookSpaceForm = ws.book_space_form ?? {};
    const terms = ws.terms ?? {};
    const dataFallback = ws.data_fallback ?? {};

    form.value = {
      show_search_bar: rundown.show_search_bar ?? true,
      show_location_filter: rundown.show_location_filter ?? true,
      show_all_rundown_details: rundown.show_all_rundown_details ?? false,
      home_sections: Object.fromEntries(
        homeSectionsCatalog.value.map((section) => [
          section.key,
          resolvedHomeSections[section.key] ?? section.default,
        ]),
      ),
      blog: {
        show_post_card_author: blog.show_post_card_author ?? false,
        show_post_card_excerpt: blog.show_post_card_excerpt ?? false,
      },
      ticket_tabs: { ...ticketTabDefaults(), ...ticketTabs },
      book_space_form: { ...bookSpaceDefaults(), ...bookSpaceForm },
      terms_last_update: terms.last_update ? new Date(terms.last_update) : null,
      data_fallback: { ...dataFallbackDefaults(), ...dataFallback },
      site_config: {
        nav: hydrateNav(ws.site_config?.nav),
        analytics: { ...analyticsDefaults(), ...ws.site_config?.analytics },
        appearance: { ...appearanceDefaults(), ...ws.site_config?.appearance },
      },
    };
    lastSavedSnapshot = JSON.stringify(buildPayload());
  } catch (err) {
    toast.error("Failed to load website settings");
  } finally {
    loading.value = false;
  }
}

async function save() {
  const payload = buildPayload();
  const snapshot = JSON.stringify(payload);

  // Nothing changed since the last save — skip the request entirely.
  if (snapshot === lastSavedSnapshot) {
    return;
  }

  // Serialize overlapping saves: queue one more run after the current finishes.
  if (saving) {
    savePending = true;
    return;
  }

  saving = true;
  try {
    await client(`/api/projects/${route.params.username}/website-settings`, {
      method: "PATCH",
      body: payload,
    });
    lastSavedSnapshot = snapshot;
    errors.value = {};
    toast.success("Website settings updated");
  } catch (err) {
    errors.value = err?.data?.errors ?? {};
    toast.error("Failed to save", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    saving = false;
    if (savePending) {
      savePending = false;
      save();
    }
  }
}

// Section toggles persist immediately on change. A deep watch covers the nested
// blog / ticket_tabs / book_space_form / terms groups too; save() no-ops via the
// snapshot so the load() reassignment never triggers a redundant request.
watch(form, () => save(), { deep: true });

onMounted(() => {
  load();
});
</script>
