<template>
  <div class="flex flex-col gap-y-6">
    <div class="space-y-1">
      <h2 class="page-title">Site Config</h2>
      <p class="page-description">
        Manage the public website's navigation, analytics, appearance, and company identity from the
        dashboard. Changes apply without a site rebuild and are saved automatically.
      </p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <Spinner class="size-5" />
    </div>

    <div v-else class="flex flex-col gap-y-4">
      <!-- Navigation -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:menu-square" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">Navigation</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Manage the header, mobile menu, and footer links on the public website. Leave a list
              empty to keep the site's built-in navigation.
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
              Set the tracking ids used on the public website. Leave a field blank to keep the site's
              built-in id, or add more than one id to send events to several properties at once.
            </p>
          </div>
        </div>

        <div class="frame-panel space-y-4 !px-4 !py-5 lg:!px-6">
          <div class="space-y-2">
            <Label for="analytics-ga4" class="text-sm font-medium tracking-tight">
              GA4 Measurement ID
            </Label>
            <AnalyticsIdListInput
              input-id="analytics-ga4"
              v-model="form.site_config.analytics.ga4"
              placeholder="G-XXXXXXXXXX"
              add-label="Add another GA4 ID"
            />
            <FieldError :errors="analyticsErrors('ga4')" />
          </div>

          <div class="space-y-2">
            <Label for="analytics-tiktok-pixel" class="text-sm font-medium tracking-tight">
              TikTok Pixel ID
            </Label>
            <AnalyticsIdListInput
              input-id="analytics-tiktok-pixel"
              v-model="form.site_config.analytics.tiktok_pixel"
              placeholder="CXXXXXXXXXXXXXXXXXXX"
              add-label="Add another TikTok pixel"
            />
            <FieldError :errors="analyticsErrors('tiktok_pixel')" />
          </div>

          <div class="space-y-2">
            <Label for="analytics-meta-pixel" class="text-sm font-medium tracking-tight">
              Meta Pixel ID
            </Label>
            <AnalyticsIdListInput
              input-id="analytics-meta-pixel"
              v-model="form.site_config.analytics.meta_pixel"
              placeholder="000000000000000"
              add-label="Add another Meta pixel"
            />
            <FieldError :errors="analyticsErrors('meta_pixel')" />
          </div>

          <div class="space-y-2">
            <Label for="analytics-gtm" class="text-sm font-medium tracking-tight">
              GTM Container ID
            </Label>
            <AnalyticsIdListInput
              input-id="analytics-gtm"
              v-model="form.site_config.analytics.gtm"
              placeholder="GTM-XXXXXXX"
              add-label="Add another GTM container"
            />
            <FieldError :errors="analyticsErrors('gtm')" />
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
              Retheme the public website with a curated color palette. Leave this off to keep the
              site's built-in palette.
            </p>
          </div>
        </div>

        <div class="frame-panel space-y-4 !px-4 !py-5 lg:!px-6">
          <div class="flex items-start justify-between gap-4">
            <div class="space-y-1">
              <Label for="appearance-enabled" class="cursor-pointer text-sm font-medium tracking-tight">
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

      <!-- Company Identity -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:building-06" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">Company Identity</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              The company name and address shown in the public website footer and legal pages. Leave
              a field blank to keep the site's built-in value.
            </p>
          </div>
        </div>

        <div class="frame-panel space-y-4 !px-4 !py-5 lg:!px-6">
          <div class="space-y-2">
            <Label for="identity-company-name" class="text-sm font-medium tracking-tight">
              Company Name
            </Label>
            <Input
              id="identity-company-name"
              v-model="form.site_config.identity.company_name"
              placeholder="e.g. Your Company Ltd"
            />
            <FieldError :errors="errors['site_config.identity.company_name']" />
          </div>

          <div class="space-y-2">
            <Label for="identity-company-address" class="text-sm font-medium tracking-tight">
              Company Address
            </Label>
            <Textarea
              id="identity-company-address"
              v-model="form.site_config.identity.company_address"
              placeholder="Street, city, postal code"
              class="min-h-20"
            />
            <FieldError :errors="errors['site_config.identity.company_address']" />
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
import { Textarea } from "@/components/ui/textarea";
import { BASE_COLOR_OPTIONS, CHART_COLOR_OPTIONS, RADII, THEME_OPTIONS } from "@/lib/appearance";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["permission"],
  permissions: ["projects.update"],
});

const props = defineProps({
  project: Object,
});

usePageMeta(null, {
  title: computed(() => `Site Config · ${props.project?.name || ""}`),
});

const route = useRoute();
const client = useSanctumClient();

const loading = ref(true);

// Curated shadcn radii, mapped to the AppearancePicker option shape.
const radiusOptions = RADII.map((r) => ({ value: r.name, label: r.title }));

const navDefaults = () => ({ header: [], dialog: [], footer: [] });
const analyticsDefaults = () => ({ ga4: [], tiktok_pixel: [], meta_pixel: [], gtm: [] });
const identityDefaults = () => ({ company_name: null, company_address: null });

// Sane starting selections for the pickers when a project has never saved a
// palette (mirrors DEFAULT_APPEARANCE's baseColor/theme/chartColor/radius).
const appearanceDefaults = () => ({
  enabled: false,
  baseColor: "neutral",
  theme: "neutral",
  chartColor: "neutral",
  radius: "default",
});

// Each analytics field is stored as null | string | string[] (a single id
// stays a plain string for legacy rows); the editor always works in the array
// form. Normalize both directions.
function toIdArray(value) {
  if (Array.isArray(value)) return value.filter((v) => typeof v === "string");
  return value ? [value] : [];
}

function normalizeAnalytics(analytics) {
  const src = analytics && typeof analytics === "object" ? analytics : {};
  return {
    ga4: toIdArray(src.ga4),
    tiktok_pixel: toIdArray(src.tiktok_pixel),
    meta_pixel: toIdArray(src.meta_pixel),
    gtm: toIdArray(src.gtm),
  };
}

// Trim rows, drop blanks, dedupe (preserving order); an empty list persists as
// null so the backend's nullable rules accept it and the site falls back to
// its baked id.
function cleanIds(rows) {
  const cleaned = (rows ?? []).map((v) => (v ?? "").trim()).filter(Boolean);
  const unique = [...new Set(cleaned)];
  return unique.length ? unique : null;
}

const form = ref({
  site_config: {
    nav: navDefaults(),
    analytics: analyticsDefaults(),
    appearance: appearanceDefaults(),
    identity: identityDefaults(),
  },
});

// Field-level validation errors from the last failed save, keyed by the
// backend's dot-notation field name (e.g. "site_config.analytics.ga4").
const errors = ref({});

// Analytics ids validate per-element, so a bad entry comes back keyed as
// "site_config.analytics.ga4.2". Collect the field-level and every element
// message under one field so <FieldError> can surface them together.
function analyticsErrors(field) {
  const prefix = `site_config.analytics.${field}`;
  return Object.entries(errors.value)
    .filter(([key]) => key === prefix || key.startsWith(`${prefix}.`))
    .flatMap(([, messages]) => messages);
}

let navKeySeed = 0;
const withNavKey = (item) => ({ ...item, _key: `nav-${navKeySeed++}` });

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
              : { label: item.label, path: item.path },
          ),
        )
      : [];

  return {
    header: toList(source.header),
    dialog: toList(source.dialog),
    footer: toList(source.footer),
  };
}

// Snapshot of the last persisted payload. Auto-save no-ops when nothing changed.
let lastSavedSnapshot = null;
let saving = false;
let savePending = false;

function buildPayload() {
  return {
    // Only the site_config slice; the backend leaves the display-toggle, SEO,
    // and legal slices it omits untouched (array_replace_recursive + guarded
    // wholesale-replace of nav/analytics).
    site_config: {
      nav: {
        header: stripNavKeys(form.value.site_config.nav.header),
        dialog: stripNavKeys(form.value.site_config.nav.dialog),
        footer: stripNavKeys(form.value.site_config.nav.footer),
      },
      analytics: {
        ga4: cleanIds(form.value.site_config.analytics.ga4),
        tiktok_pixel: cleanIds(form.value.site_config.analytics.tiktok_pixel),
        meta_pixel: cleanIds(form.value.site_config.analytics.meta_pixel),
        gtm: cleanIds(form.value.site_config.analytics.gtm),
      },
      appearance: { ...form.value.site_config.appearance },
      identity: {
        company_name: blankToNull(form.value.site_config.identity.company_name),
        company_address: blankToNull(form.value.site_config.identity.company_address),
      },
    },
  };
}

async function load() {
  loading.value = true;
  try {
    const response = await client(`/api/projects/${route.params.username}`);
    const ws = response.data?.settings?.website_settings ?? {};
    form.value = {
      site_config: {
        nav: hydrateNav(ws.site_config?.nav),
        analytics: normalizeAnalytics(ws.site_config?.analytics),
        appearance: { ...appearanceDefaults(), ...ws.site_config?.appearance },
        identity: { ...identityDefaults(), ...ws.site_config?.identity },
      },
    };
    lastSavedSnapshot = JSON.stringify(buildPayload());
  } catch (err) {
    toast.error("Failed to load site config");
  } finally {
    loading.value = false;
  }
}

async function save() {
  const payload = buildPayload();
  const snapshot = JSON.stringify(payload);

  if (snapshot === lastSavedSnapshot) {
    return;
  }

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
    toast.success("Site config updated");
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

// This page mixes free-text (company name/address, nav labels/urls, analytics
// ids) with structured pickers, so debounce the deep-watch save ~800ms instead
// of firing a PATCH per keystroke. save() no-ops via the snapshot so the load()
// reassignment never triggers a request.
let saveTimer = null;
watch(
  form,
  () => {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => save(), 800);
  },
  { deep: true },
);

onBeforeUnmount(() => clearTimeout(saveTimer));

onMounted(() => {
  load();
});
</script>
