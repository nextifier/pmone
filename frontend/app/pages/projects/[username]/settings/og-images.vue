<template>
  <div class="flex flex-col gap-y-6">
    <div class="flex flex-col gap-y-4 sm:flex-row sm:items-start sm:justify-between sm:gap-x-4">
      <div class="space-y-1">
        <h2 class="page-title">OG Images</h2>
        <p class="page-description">
          Social share image, title, and description for each page of the event website. Pages
          without an image here fall back to the automatically generated share card.
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

    <div v-if="loading" class="space-y-6">
      <Skeleton v-for="n in 3" :key="n" class="h-64 w-full rounded-xl" />
    </div>

    <template v-else>
      <div
        v-if="!websiteUrl"
        class="border-border bg-muted/50 flex items-start gap-x-2.5 rounded-xl border p-4"
      >
        <Icon name="hugeicons:alert-circle" class="text-muted-foreground mt-0.5 size-5 shrink-0" />
        <p class="text-muted-foreground text-sm tracking-tight">
          This project has no "Website" link yet. Add one in General settings to enable capturing
          pages directly from the live website.
        </p>
      </div>

      <OgPageCard
        v-for="page in pages"
        :key="page.key"
        :page-key="page.key"
        :label="page.label"
        :path="page.path"
        :username="username"
        :website-url="websiteUrl"
        :initial="ogPages[page.key]"
      />
    </template>
  </div>
</template>

<script setup>
import OgPageCard from "@/components/og/OgPageCard.vue";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["permission"],
  permissions: ["projects.update"],
});

const props = defineProps({
  project: Object,
});

usePageMeta(null, {
  title: computed(() => `OG Images · ${props.project?.name || ""}`),
});

const route = useRoute();
const client = useSanctumClient();

const username = computed(() => route.params.username);

const pages = [
  { key: "home", label: "Home", path: "/" },
  { key: "brands", label: "Brands", path: "/brands" },
  { key: "rundown", label: "Rundown", path: "/rundown" },
  { key: "programs", label: "Programs", path: "/programs" },
  { key: "contact", label: "Contact", path: "/contact" },
  { key: "book-space", label: "Book Space", path: "/book-space" },
  { key: "tickets", label: "Tickets", path: "/tickets" },
  { key: "gallery", label: "Gallery", path: "/gallery" },
  { key: "partners", label: "Partners", path: "/partners" },
  { key: "winner", label: "Winner", path: "/winner" },
  { key: "guests", label: "Guests", path: "/guests" },
];

const loading = ref(true);
const websiteUrl = ref(null);
const ogPages = ref({});

async function load() {
  loading.value = true;
  try {
    const res = await client(`/api/projects/${username.value}/og-images`);
    websiteUrl.value = res?.website_url ?? null;
    ogPages.value = res?.pages ?? {};
  } catch (err) {
    console.error("Failed to load OG images:", err);
  } finally {
    loading.value = false;
  }
}

onMounted(load);

// Capture every static page in one queued batch (chained Browsershot jobs)
const captureAll = useJobProgress();

const captureAllLabel = computed(() => {
  if (!captureAll.processing.value) return "Capture all pages";
  const p = captureAll.progress.value;
  return p?.message || `Capturing... ${p?.percentage ?? 0}%`;
});

const captureAllTooltip = computed(() =>
  websiteUrl.value
    ? "Screenshot every static page of the live website as its OG image"
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
      await load();
    } else if (status === "failed") {
      toast.error("Capture failed", {
        description: captureAll.progress.value?.error_message || "Please try again.",
      });
      captureAll.reset();
    }
  },
);
</script>
