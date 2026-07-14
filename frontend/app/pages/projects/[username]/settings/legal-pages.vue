<template>
  <div class="flex flex-col gap-y-6">
    <div class="space-y-1">
      <h2 class="page-title">Legal Pages</h2>
      <p class="page-description">
        Each legal page falls back to the website's built-in template. Write content here only to
        replace it - leave a page blank to keep the default, vetted copy. Changes apply without a
        site rebuild.
      </p>
    </div>

    <div v-if="loading" class="flex flex-col gap-y-4">
      <Skeleton v-for="n in 3" :key="n" class="h-56 w-full rounded-xl" />
    </div>

    <div v-else class="flex flex-col gap-y-4">
      <!-- Locale switcher (shared across all page editors) -->
      <Tabs v-model="activeLocale" variant="segmented" class="self-start">
        <TabsList>
          <TabsIndicator />
          <TabsTrigger v-for="locale in LOCALES" :key="locale.value" :value="locale.value">
            {{ locale.label }}
          </TabsTrigger>
        </TabsList>
      </Tabs>

      <div v-for="page in PAGES" :key="page.key" class="frame">
        <div
          class="flex flex-col gap-y-3 px-3 py-3 sm:flex-row sm:items-start sm:justify-between lg:px-5"
        >
          <div class="flex items-start gap-x-2.5">
            <Icon :name="page.icon" class="mt-0.5 size-5 shrink-0" />
            <div class="min-w-0 space-y-1">
              <div class="flex items-center gap-x-2">
                <h3 class="text-base font-semibold tracking-tight">{{ page.label }}</h3>
                <Badge :variant="statusFor(page.key).variant" plain>
                  {{ statusFor(page.key).label }}
                </Badge>
              </div>
              <p class="text-muted-foreground text-sm tracking-tight">{{ page.description }}</p>
            </div>
          </div>

          <a
            v-if="liveUrlFor(page.key)"
            :href="liveUrlFor(page.key)"
            target="_blank"
            rel="noopener noreferrer"
            class="text-muted-foreground hover:text-foreground inline-flex shrink-0 items-center gap-x-1 text-xs tracking-tight transition-colors sm:text-sm"
          >
            <Icon name="hugeicons:link-square-02" class="size-4" />
            View live
          </a>
        </div>

        <div class="frame-panel space-y-4 !px-4 !py-5 lg:!px-6">
          <TipTapEditor
            v-model="bodies[page.key][activeLocale]"
            :sticky="false"
            min-height="180px"
            :placeholder="`${page.label} content (rich text)`"
          />

          <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-col gap-2">
              <Label class="text-sm font-medium tracking-tight">Last updated</Label>
              <DatePicker
                v-model="dates[page.key]"
                placeholder="Select a date"
                class="max-w-xs"
              />
            </div>

            <div class="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                :disabled="loadingTemplateKey === page.key"
                @click="requestLoadTemplate(page.key)"
              >
                <Spinner v-if="loadingTemplateKey === page.key" class="size-4" />
                <Icon v-else name="hugeicons:file-import" />
                Load default template
              </Button>
              <Button size="sm" :disabled="savingKey === page.key" @click="savePage(page.key)">
                <Spinner v-if="savingKey === page.key" class="size-4" />
                Save
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Overwrite confirmation for "Load default template" -->
    <DialogResponsive v-model:open="templateDialogOpen" class="h-full">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tighter">
            Replace the current content?
          </div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            The {{ activeLocaleLabel }} editor for this page already has content. Loading the default
            template will replace it. You can still undo before saving.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" @click="templateDialogOpen = false">Cancel</Button>
            <Button @click="confirmLoadTemplate">Load template</Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
import { DialogResponsive } from "@/components/ui/dialog-responsive";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { TipTapEditor } from "@/components/ui/tip-tap-editor";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["permission"],
  permissions: ["projects.update"],
});

const props = defineProps({
  project: Object,
});

usePageMeta(null, {
  title: computed(() => `Legal Pages · ${props.project?.name || ""}`),
});

const route = useRoute();
const client = useSanctumClient();

const LOCALES = [
  { value: "en", label: "English" },
  { value: "id", label: "Indonesian" },
  { value: "ja", label: "日本語" },
  { value: "ko", label: "한국어" },
  { value: "zh", label: "中文" },
];

// The six legal/policy page keys. Must match App\Models\WebsitePage::KEYS. The
// path is the public route the "View live" link points at.
const PAGES = [
  {
    key: "terms",
    label: "Terms of Service",
    icon: "hugeicons:legal-document-01",
    description: "The Terms of Service page.",
    path: "/terms",
  },
  {
    key: "privacy",
    label: "Privacy Policy",
    icon: "hugeicons:shield-01",
    description: "The Privacy Policy page.",
    path: "/privacy",
  },
  {
    key: "event-policy",
    label: "Event Policy",
    icon: "hugeicons:calendar-03",
    description: "The Event Policy page.",
    path: "/event-policy",
  },
  {
    key: "help-center",
    label: "Help Center",
    icon: "hugeicons:help-circle",
    description: "The Help Center page.",
    path: "/help-center",
  },
  {
    key: "ticket-terms-and-conditions",
    label: "Ticket Terms & Conditions",
    icon: "hugeicons:ticket-01",
    description: "Terms and conditions for ticket purchases.",
    path: "/ticket-terms-and-conditions",
  },
  {
    key: "ticket-refund-and-return-policy",
    label: "Ticket Refund & Return Policy",
    icon: "hugeicons:refund",
    description: "Refund and return policy for ticket purchases.",
    path: "/ticket-refund-and-return-policy",
  },
];

const EMPTY_BODY = () => ({ en: "", id: "", ja: "", ko: "", zh: "" });

const activeLocale = ref("en");
const loading = ref(true);
const savingKey = ref(null);
const loadingTemplateKey = ref(null);
const websiteUrl = ref(null);

// bodies[pageKey][locale] = html string; dates[pageKey] = Date | null. Both
// seeded for every key so the editors render before load resolves.
const bodies = ref(Object.fromEntries(PAGES.map((p) => [p.key, EMPTY_BODY()])));
const dates = ref(Object.fromEntries(PAGES.map((p) => [p.key, null])));

const activeLocaleLabel = computed(
  () => LOCALES.find((l) => l.value === activeLocale.value)?.label ?? "",
);

// TipTap emits an "empty" document as tag-only markup ("<p></p>"). Treat any
// body with no visible text as blank so it (a) reads as the built-in template,
// not "Customized", and (b) saves as null - so the site fails open to the baked
// copy instead of rendering an empty override (the legal-page-never-empty rule).
function isBlankHtml(html) {
  return (html ?? "")
    .replace(/<[^>]*>/g, "")
    .replace(/&(nbsp|#160);/gi, "")
    .trim() === "";
}

// A page is "customized" once any locale has a non-blank body. The badge lists
// the filled locales so coverage is visible at a glance (the locale switcher is
// shared across pages, so per-page coverage lives on the badge, not the tabs).
function filledLocales(key) {
  return LOCALES.filter((l) => !isBlankHtml(bodies.value[key]?.[l.value]));
}

function statusFor(key) {
  const filled = filledLocales(key);
  if (filled.length === 0) {
    return { variant: "muted", label: "Built-in template" };
  }
  return {
    variant: "success",
    label: `Customized · ${filled.map((l) => l.value.toUpperCase()).join(", ")}`,
  };
}

function liveUrlFor(key) {
  if (!websiteUrl.value) return null;
  const path = PAGES.find((p) => p.key === key)?.path ?? "";
  return `${websiteUrl.value.replace(/\/$/, "")}${path}`;
}

// Send blank locales as null so the backend/site fails open to the baked copy
// for that language instead of rendering an empty legal page.
function toPayloadBody(body) {
  const out = {};
  for (const { value } of LOCALES) {
    out[value] = isBlankHtml(body?.[value]) ? null : body[value];
  }
  return out;
}

function toIsoDate(date) {
  if (!date) return null;
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

async function load() {
  loading.value = true;
  try {
    const response = await client(`/api/projects/${route.params.username}/website-pages`);
    const data = response.data ?? {};
    websiteUrl.value = response.website_url ?? null;
    const nextBodies = Object.fromEntries(PAGES.map((p) => [p.key, EMPTY_BODY()]));
    const nextDates = Object.fromEntries(PAGES.map((p) => [p.key, null]));
    for (const page of PAGES) {
      const saved = data[page.key]?.body ?? {};
      nextBodies[page.key] = { ...EMPTY_BODY(), ...saved };
      const iso = data[page.key]?.last_updated_at;
      nextDates[page.key] = iso ? new Date(`${iso}T00:00:00`) : null;
    }
    bodies.value = nextBodies;
    dates.value = nextDates;
  } catch (err) {
    toast.error("Failed to load legal pages");
  } finally {
    loading.value = false;
  }
}

async function savePage(key) {
  savingKey.value = key;
  try {
    await client(`/api/projects/${route.params.username}/website-pages/${key}`, {
      method: "PUT",
      body: {
        body: toPayloadBody(bodies.value[key]),
        last_updated_at: toIsoDate(dates.value[key]),
      },
    });
    toast.success("Page updated");
  } catch (err) {
    toast.error("Failed to save", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    savingKey.value = null;
  }
}

// "Load default template" fills the current locale's editor with the built-in
// copy. Confirm first if that editor already has content so an accidental click
// never wipes work.
const templateDialogOpen = ref(false);
const pendingTemplateKey = ref(null);

function requestLoadTemplate(key) {
  // Only confirm when the editor holds real, visible content - an empty "<p></p>"
  // counts as blank (same rule as the status badge), so loading over it is safe.
  if (!isBlankHtml(bodies.value[key]?.[activeLocale.value])) {
    pendingTemplateKey.value = key;
    templateDialogOpen.value = true;
  } else {
    doLoadTemplate(key);
  }
}

function confirmLoadTemplate() {
  const key = pendingTemplateKey.value;
  templateDialogOpen.value = false;
  pendingTemplateKey.value = null;
  if (key) doLoadTemplate(key);
}

async function doLoadTemplate(key) {
  loadingTemplateKey.value = key;
  try {
    const res = await client(
      `/api/projects/${route.params.username}/website-pages/${key}/template`,
    );
    bodies.value[key][activeLocale.value] = res.data?.body ?? "";
    toast.success("Default template loaded", {
      description: "Review, edit, then Save to apply it to the live site.",
    });
  } catch (err) {
    toast.error("Failed to load template", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    loadingTemplateKey.value = null;
  }
}

onMounted(() => {
  load();
});
</script>
