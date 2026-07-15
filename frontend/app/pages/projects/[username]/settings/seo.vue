<template>
  <div class="flex flex-col gap-y-6">
    <div class="flex flex-col gap-y-4 sm:flex-row sm:items-start sm:justify-between sm:gap-x-4">
      <div class="space-y-1">
        <h2 class="page-title">SEO</h2>
        <p class="page-description">
          Search title, meta description, and social share preview for each page of the public
          website. Changes apply without a site rebuild. Leave a field blank to keep the site's
          built-in copy.
        </p>
      </div>

      <Button
        v-if="!loading"
        variant="outline"
        class="shrink-0"
        :disabled="!websiteUrl || captureAll.processing.value"
        v-tippy="captureAllTooltip"
        @click="startCaptureAll"
      >
        <Spinner v-if="captureAll.processing.value" />
        <Icon v-else name="hugeicons:camera-01" />
        <span>{{ captureAllLabel }}</span>
      </Button>
    </div>

    <div v-if="loading" class="space-y-4">
      <Skeleton class="h-[32rem] w-full rounded-xl" />
    </div>

    <template v-else>
      <div
        v-if="!websiteUrl"
        class="border-border bg-muted/50 flex items-start gap-x-2.5 rounded-xl border p-4"
      >
        <Icon name="hugeicons:alert-circle" class="text-muted-foreground mt-0.5 size-5 shrink-0" />
        <p class="text-muted-foreground text-sm tracking-tight">
          This project has no "Website" link yet. Add one in General settings to enable capturing
          share images directly from the live website.
        </p>
      </div>

      <div class="flex flex-col gap-4 md:flex-row md:items-start">
        <!-- Master: page picker (Select on mobile, rail on desktop) -->
        <div class="md:hidden">
          <Select :model-value="selectedKey" @update:model-value="selectPage">
            <SelectTrigger class="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="page in SEO_PAGES" :key="page.key" :value="page.key">
                {{ page.label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <nav class="hidden shrink-0 md:block md:w-44">
          <ul class="space-y-0.5">
            <li v-for="page in SEO_PAGES" :key="page.key">
              <button
                type="button"
                class="flex w-full items-center gap-x-2 rounded-lg px-2.5 py-2 text-left text-sm tracking-tight transition-colors"
                :class="
                  page.key === selectedKey
                    ? 'bg-muted text-foreground font-medium'
                    : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'
                "
                @click="selectPage(page.key)"
              >
                <Icon :name="page.icon" class="size-4 shrink-0" />
                <span class="truncate">{{ page.label }}</span>
              </button>
            </li>
          </ul>
        </nav>

        <!-- Detail: selected page editor -->
        <div class="min-w-0 flex-1">
          <div class="frame">
            <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
              <Icon :name="selectedPage.icon" class="mt-0.5 size-5 shrink-0" />
              <div class="min-w-0 space-y-1">
                <h3 class="text-base font-semibold tracking-tight">{{ selectedPage.label }}</h3>
                <p class="text-muted-foreground text-sm tracking-tight">
                  {{ selectedPage.description }}
                </p>
              </div>
            </div>

            <div class="frame-panel space-y-6 !px-4 !py-5 lg:!px-6">
              <!-- Search result meta (per locale) -->
              <div v-if="selectedPage.copyKey" class="space-y-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                  <h4 class="text-sm font-semibold tracking-tighter">Search result</h4>
                  <SettingsLocaleTabs v-model="activeLocale" />
                </div>

                <div class="space-y-2">
                  <Label :for="`${selectedPage.key}-title`" class="text-sm font-medium tracking-tight">
                    Title
                  </Label>
                  <Input
                    :id="`${selectedPage.key}-title`"
                    v-model="copyFields[selectedPage.copyKey].title[activeLocale]"
                    placeholder="Page <title>"
                    maxlength="300"
                  />
                </div>

                <div class="space-y-2">
                  <Label
                    :for="`${selectedPage.key}-description`"
                    class="text-sm font-medium tracking-tight"
                  >
                    Meta description
                  </Label>
                  <Textarea
                    :id="`${selectedPage.key}-description`"
                    v-model="copyFields[selectedPage.copyKey].description[activeLocale]"
                    placeholder="Meta description"
                    class="min-h-20"
                    maxlength="300"
                  />
                </div>
              </div>

              <!-- Social share (Open Graph) -->
              <div
                v-if="selectedPage.ogKey"
                class="space-y-4"
                :class="{ 'border-t pt-6': selectedPage.copyKey }"
              >
                <div class="space-y-1">
                  <h4 class="text-sm font-semibold tracking-tighter">Social share</h4>
                  <p class="text-muted-foreground text-sm tracking-tight">
                    The image, title, and description shown when this page is shared on social media.
                    Single image per page (not translated).
                  </p>
                </div>

                <OgPageCard
                  :key="selectedPage.ogKey"
                  ref="ogRef"
                  :page-key="selectedPage.ogKey"
                  :label="selectedPage.label"
                  :path="selectedPage.ogPath"
                  :username="username"
                  :website-url="websiteUrl"
                  :initial="ogInitial[selectedPage.ogKey] ?? {}"
                />
              </div>

              <div class="flex items-center justify-end border-t pt-4">
                <Button size="sm" :disabled="saving" @click="savePanel">
                  <Spinner v-if="saving" class="size-4" />
                  Save
                </Button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Unsaved-changes guard when switching pages -->
    <DialogResponsive v-model:open="switchDialogOpen" class="h-full">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tighter">
            Discard unsaved changes?
          </div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            This page has changes you haven't saved yet. Switching pages will discard them.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" @click="switchDialogOpen = false">Keep editing</Button>
            <Button variant="destructive" @click="confirmSwitch">Discard</Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import OgPageCard from "@/components/og/OgPageCard.vue";
import SettingsLocaleTabs from "@/components/project/SettingsLocaleTabs.vue";
import { Button } from "@/components/ui/button";
import { DialogResponsive } from "@/components/ui/dialog-responsive";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
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
  title: computed(() => `SEO · ${props.project?.name || ""}`),
});

const route = useRoute();
const client = useSanctumClient();

const username = computed(() => route.params.username);

// Unified page catalog. `copyKey` maps to App\Models\WebsiteCopy::PAGE_KEYS
// (SEO meta), `ogKey` to App\Support\OgPages::KEYS (share images). A page may
// have one or both. Order groups the pages that have a share image first.
const SEO_PAGES = [
  { label: "Home", icon: "hugeicons:home-01", description: "The public website's home page.", copyKey: "home", ogKey: "home", ogPath: "/" },
  { label: "Brands", icon: "hugeicons:store-02", description: "The brands/exhibitors listing page.", copyKey: "brands", ogKey: "brands", ogPath: "/brands" },
  { label: "Rundown", icon: "hugeicons:time-schedule", description: "The event schedule/rundown page.", copyKey: "rundown", ogKey: "rundown", ogPath: "/rundown" },
  { label: "Programs", icon: "hugeicons:presentation-bar-chart-01", description: "The main programs page.", copyKey: "programs", ogKey: "programs", ogPath: "/programs" },
  { label: "Contact", icon: "hugeicons:mail-02", description: "The contact us page.", copyKey: "contact", ogKey: "contact", ogPath: "/contact" },
  { label: "Book Space", icon: "hugeicons:shopping-bag-02", description: "The exhibitor space booking page.", copyKey: "bookSpace", ogKey: "book-space", ogPath: "/book-space" },
  { label: "Tickets", icon: "hugeicons:ticket-01", description: "The ticket purchase page.", copyKey: "ticket", ogKey: "tickets", ogPath: "/tickets" },
  { label: "Gallery", icon: "hugeicons:image-02", description: "The event photo gallery page.", copyKey: "gallery", ogKey: "gallery", ogPath: "/gallery" },
  { label: "Partners", icon: "hugeicons:dim-sum-02", description: "The partners/sponsors page.", copyKey: "partners", ogKey: "partners", ogPath: "/partners" },
  { label: "Guests", icon: "hugeicons:user-multiple-02", description: "The guests/speakers page.", copyKey: null, ogKey: "guests", ogPath: "/guests" },
  { label: "FAQ", icon: "hugeicons:help-circle", description: "The frequently asked questions page.", copyKey: "faq", ogKey: null },
  { label: "Links", icon: "hugeicons:link-01", description: "The links page.", copyKey: "links", ogKey: null },
  { label: "News", icon: "hugeicons:news", description: "The news/blog listing page.", copyKey: "news", ogKey: null },
  { label: "Ticket Policy", icon: "hugeicons:refund", description: "The ticket policy page.", copyKey: "ticketPolicy", ogKey: null },
  { label: "Event Policy", icon: "hugeicons:calendar-03", description: "The event policy page.", copyKey: "eventPolicy", ogKey: null },
  { label: "Terms of Service", icon: "hugeicons:legal-document-01", description: "The Terms of Service page.", copyKey: "terms", ogKey: null },
  { label: "Privacy Policy", icon: "hugeicons:shield-01", description: "The Privacy Policy page.", copyKey: "privacy", ogKey: null },
  { label: "Random Winner Generator", icon: "hugeicons:dice", description: "The Random Winner Generator page.", copyKey: "winner", ogKey: null },
].map((p) => ({ ...p, key: p.copyKey ?? p.ogKey }));

const EMPTY_LOCALE_MAP = () => Object.fromEntries(WEBSITE_LOCALES.map((l) => [l.value, ""]));
const EMPTY_PAGE_FIELDS = () => ({ title: EMPTY_LOCALE_MAP(), description: EMPTY_LOCALE_MAP() });

const loading = ref(true);
const saving = ref(false);
const activeLocale = ref("en");
const selectedKey = ref(SEO_PAGES[0].key);
const websiteUrl = ref(null);

// copyFields[copyKey] = { title: {locale}, description: {locale} }; ogInitial
// [ogKey] = { title, description, image }. copySnapshot holds the loaded JSON
// per copyKey so we can tell the selected page's meta apart from a pristine load.
const copyFields = ref({});
const copySnapshot = ref({});
const ogInitial = ref({});

const ogRef = ref(null);

const selectedPage = computed(
  () => SEO_PAGES.find((p) => p.key === selectedKey.value) ?? SEO_PAGES[0],
);

const copyDirty = computed(() => {
  const key = selectedPage.value?.copyKey;
  if (!key || !copyFields.value[key]) return false;
  return JSON.stringify(copyFields.value[key]) !== copySnapshot.value[key];
});

function toLocalePayload(localeMap) {
  const out = {};
  for (const { value } of WEBSITE_LOCALES) {
    out[value] = blankToNull(localeMap?.[value]);
  }
  return out;
}

async function load() {
  loading.value = true;
  try {
    const [copyRes, ogRes] = await Promise.all([
      client(`/api/projects/${username.value}/website-copy`),
      client(`/api/projects/${username.value}/og-images`),
    ]);

    const copyData = copyRes.data ?? {};
    const nextCopy = {};
    const nextSnap = {};
    for (const page of SEO_PAGES) {
      if (!page.copyKey) continue;
      nextCopy[page.copyKey] = {
        title: { ...EMPTY_LOCALE_MAP(), ...(copyData[page.copyKey]?.title ?? {}) },
        description: { ...EMPTY_LOCALE_MAP(), ...(copyData[page.copyKey]?.description ?? {}) },
      };
      nextSnap[page.copyKey] = JSON.stringify(nextCopy[page.copyKey]);
    }
    copyFields.value = nextCopy;
    copySnapshot.value = nextSnap;

    websiteUrl.value = ogRes?.website_url ?? null;
    ogInitial.value = ogRes?.pages ?? {};
  } catch (err) {
    toast.error("Failed to load SEO settings");
  } finally {
    loading.value = false;
  }
}

async function saveCopy(copyKey) {
  await Promise.all(
    ["title", "description"].map((field) =>
      client(`/api/projects/${username.value}/website-copy/${copyKey}/${field}`, {
        method: "PUT",
        body: { value: toLocalePayload(copyFields.value[copyKey][field]) },
      }),
    ),
  );
  copySnapshot.value[copyKey] = JSON.stringify(copyFields.value[copyKey]);
}

async function savePanel() {
  const page = selectedPage.value;
  const doCopy = !!page.copyKey && copyDirty.value;
  const doOg = !!page.ogKey && !!ogRef.value?.isDirty?.();

  if (!doCopy && !doOg) {
    toast.info("No changes to save");
    return;
  }

  saving.value = true;
  try {
    const jobs = [];
    if (doCopy) jobs.push(saveCopy(page.copyKey));
    if (doOg) jobs.push(ogRef.value.save());
    await Promise.all(jobs);
    toast.success("SEO settings saved");
  } catch (err) {
    toast.error("Failed to save", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    saving.value = false;
  }
}

// Switching pages discards the selected page's unsaved edits, so guard it.
const switchDialogOpen = ref(false);
const pendingKey = ref(null);

function isCurrentDirty() {
  return copyDirty.value || (!!selectedPage.value?.ogKey && !!ogRef.value?.isDirty?.());
}

function selectPage(key) {
  if (!key || key === selectedKey.value) return;
  if (isCurrentDirty()) {
    pendingKey.value = key;
    switchDialogOpen.value = true;
  } else {
    selectedKey.value = key;
  }
}

function confirmSwitch() {
  const cur = selectedPage.value;
  if (cur?.copyKey && copySnapshot.value[cur.copyKey]) {
    copyFields.value[cur.copyKey] = JSON.parse(copySnapshot.value[cur.copyKey]);
  }
  switchDialogOpen.value = false;
  if (pendingKey.value) selectedKey.value = pendingKey.value;
  pendingKey.value = null;
}

// "Capture all pages" — queued Browsershot batch, then refresh the OG images.
const captureAll = useJobProgress();

const captureAllLabel = computed(() => {
  if (!captureAll.processing.value) return "Capture all pages";
  const p = captureAll.progress.value;
  return p?.message || `Capturing... ${p?.percentage ?? 0}%`;
});

const captureAllTooltip = computed(() =>
  websiteUrl.value
    ? "Screenshot every static page of the live website as its share image"
    : "Add a Website link to the project first",
);

async function startCaptureAll() {
  try {
    await captureAll.startJob(`/api/projects/${username.value}/og-images/capture-all`);
  } catch (err) {
    captureAll.reset();
    toast.error("Failed to start capture", {
      description: err?.data?.message || err?.message,
    });
  }
}

async function reloadOg() {
  try {
    const ogRes = await client(`/api/projects/${username.value}/og-images`);
    websiteUrl.value = ogRes?.website_url ?? null;
    ogInitial.value = ogRes?.pages ?? {};
  } catch (err) {
    // A failed refresh just leaves the last-known images in place.
  }
}

watch(
  () => captureAll.progress.value?.status,
  async (status) => {
    if (status === "completed") {
      const failed = captureAll.progress.value?.failed_keys ?? [];
      if (failed.length) {
        toast.warning("Capture finished with failures", {
          description: `Failed pages: ${failed.join(", ")}`,
        });
      } else {
        toast.success("All pages captured");
      }
      captureAll.reset();
      await reloadOg();
    } else if (status === "failed") {
      toast.error("Capture failed", {
        description: captureAll.progress.value?.error_message || "Please try again.",
      });
      captureAll.reset();
    }
  },
);

onMounted(load);
</script>
