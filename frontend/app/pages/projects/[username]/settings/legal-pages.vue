<template>
  <div class="flex flex-col gap-y-6">
    <div class="space-y-1">
      <h2 class="page-title">Legal Pages</h2>
      <p class="page-description">
        Override the legal and policy pages on the public website. Changes apply without a site
        rebuild. Leave a page blank to keep the site's built-in, vetted copy.
      </p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <Spinner class="size-5" />
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
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon :name="page.icon" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">{{ page.label }}</h3>
            <p class="text-muted-foreground text-sm tracking-tight">{{ page.description }}</p>
          </div>
        </div>

        <div class="frame-panel space-y-3 !px-4 !py-5 lg:!px-6">
          <TipTapEditor
            v-model="bodies[page.key][activeLocale]"
            :sticky="false"
            min-height="180px"
            :placeholder="`${page.label} content (rich text)`"
          />
          <div class="flex items-center justify-end">
            <Button size="sm" :disabled="savingKey === page.key" @click="savePage(page.key)">
              <Spinner v-if="savingKey === page.key" class="size-4" />
              Save
            </Button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
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

// The six legal/policy page keys. Must match App\Models\WebsitePage::KEYS.
const PAGES = [
  {
    key: "terms",
    label: "Terms of Service",
    icon: "hugeicons:legal-document-01",
    description: "The Terms of Service page.",
  },
  {
    key: "privacy",
    label: "Privacy Policy",
    icon: "hugeicons:shield-01",
    description: "The Privacy Policy page.",
  },
  {
    key: "event-policy",
    label: "Event Policy",
    icon: "hugeicons:calendar-03",
    description: "The Event Policy page.",
  },
  {
    key: "help-center",
    label: "Help Center",
    icon: "hugeicons:help-circle",
    description: "The Help Center page.",
  },
  {
    key: "ticket-terms-and-conditions",
    label: "Ticket Terms & Conditions",
    icon: "hugeicons:ticket-01",
    description: "Terms and conditions for ticket purchases.",
  },
  {
    key: "ticket-refund-and-return-policy",
    label: "Ticket Refund & Return Policy",
    icon: "hugeicons:refund",
    description: "Refund and return policy for ticket purchases.",
  },
];

const EMPTY_BODY = () => ({ en: "", id: "", ja: "", ko: "", zh: "" });

const activeLocale = ref("en");
const loading = ref(true);
const savingKey = ref(null);

// bodies[pageKey][locale] = html string. Seeded with every key so the editors
// always render even before load resolves.
const bodies = ref(Object.fromEntries(PAGES.map((p) => [p.key, EMPTY_BODY()])));

// Send blank locales as null so the backend/site fails open to the baked copy
// for that language instead of rendering an empty legal page.
function toPayloadBody(body) {
  const out = {};
  for (const { value } of LOCALES) {
    const v = (body?.[value] ?? "").trim();
    out[value] = v === "" ? null : body[value];
  }
  return out;
}

async function load() {
  loading.value = true;
  try {
    const response = await client(`/api/projects/${route.params.username}/website-pages`);
    const data = response.data ?? {};
    const next = Object.fromEntries(PAGES.map((p) => [p.key, EMPTY_BODY()]));
    for (const page of PAGES) {
      const saved = data[page.key]?.body ?? {};
      next[page.key] = { ...EMPTY_BODY(), ...saved };
    }
    bodies.value = next;
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
      body: { body: toPayloadBody(bodies.value[key]) },
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

onMounted(() => {
  load();
});
</script>
