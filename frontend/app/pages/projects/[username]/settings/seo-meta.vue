<template>
  <div class="flex flex-col gap-y-6">
    <div class="space-y-1">
      <h2 class="page-title">SEO Meta</h2>
      <p class="page-description">
        Override the &lt;title&gt; and meta description for select pages on the public website.
        Changes apply without a site rebuild. Leave a field blank to keep the site's built-in
        copy.
      </p>
      <p class="text-muted-foreground text-xs tracking-tight">
        Spike preview (plan 012): Home and Brands only. A full page/section editor is a follow-up.
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

        <div class="frame-panel space-y-4 !px-4 !py-5 lg:!px-6">
          <div class="space-y-2">
            <Label :for="`${page.key}-title`" class="text-sm font-medium tracking-tight">
              Title
            </Label>
            <Input
              :id="`${page.key}-title`"
              v-model="fields[page.key].title[activeLocale]"
              placeholder="Page <title>"
              maxlength="300"
            />
          </div>

          <div class="space-y-2">
            <Label :for="`${page.key}-description`" class="text-sm font-medium tracking-tight">
              Meta description
            </Label>
            <Textarea
              :id="`${page.key}-description`"
              v-model="fields[page.key].description[activeLocale]"
              placeholder="Meta description"
              class="min-h-20"
              maxlength="300"
            />
          </div>

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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["permission"],
  permissions: ["projects.update"],
});

const props = defineProps({
  project: Object,
});

usePageMeta(null, {
  title: computed(() => `SEO Meta · ${props.project?.name || ""}`),
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

// The spike's two page keys. Must match App\Models\WebsiteCopy::PAGE_KEYS.
const PAGES = [
  {
    key: "home",
    label: "Home",
    icon: "hugeicons:home-01",
    description: "The public website's home page.",
  },
  {
    key: "brands",
    label: "Brands",
    icon: "hugeicons:store-01",
    description: "The public website's brands/exhibitors listing page.",
  },
];

const EMPTY_LOCALE_MAP = () => ({ en: "", id: "", ja: "", ko: "", zh: "" });
const EMPTY_PAGE_FIELDS = () => ({ title: EMPTY_LOCALE_MAP(), description: EMPTY_LOCALE_MAP() });

const activeLocale = ref("en");
const loading = ref(true);
const savingKey = ref(null);

// fields[pageKey][field][locale] = string. Seeded with every page/field so
// the editors always render even before load resolves.
const fields = ref(Object.fromEntries(PAGES.map((p) => [p.key, EMPTY_PAGE_FIELDS()])));

// A blank input becomes "" via v-model, but the backend's nullable rules
// only accept null or a non-empty string - never send an empty string
// through, so the site fails open to the baked copy for that language.
function toPayloadValue(localeMap) {
  const out = {};
  for (const { value } of LOCALES) {
    const v = (localeMap?.[value] ?? "").trim();
    out[value] = v === "" ? null : localeMap[value];
  }
  return out;
}

async function load() {
  loading.value = true;
  try {
    const response = await client(`/api/projects/${route.params.username}/website-copy`);
    const data = response.data ?? {};
    const next = Object.fromEntries(PAGES.map((p) => [p.key, EMPTY_PAGE_FIELDS()]));
    for (const page of PAGES) {
      const savedTitle = data[page.key]?.title ?? {};
      const savedDescription = data[page.key]?.description ?? {};
      next[page.key] = {
        title: { ...EMPTY_LOCALE_MAP(), ...savedTitle },
        description: { ...EMPTY_LOCALE_MAP(), ...savedDescription },
      };
    }
    fields.value = next;
  } catch (err) {
    toast.error("Failed to load SEO meta");
  } finally {
    loading.value = false;
  }
}

async function savePage(pageKey) {
  savingKey.value = pageKey;
  try {
    await Promise.all(
      ["title", "description"].map((field) =>
        client(`/api/projects/${route.params.username}/website-copy/${pageKey}/${field}`, {
          method: "PUT",
          body: { value: toPayloadValue(fields.value[pageKey][field]) },
        })
      )
    );
    toast.success("SEO meta updated");
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
