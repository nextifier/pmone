<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-4xl">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:carousel-horizontal-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Banners</h1>
        <span
          v-if="!loading && totalCount"
          class="text-muted-foreground text-sm tracking-tight tabular-nums"
        >
          {{ totalCount }}
        </span>
      </div>

      <Button v-if="canCreate" size="sm" @click="openCreate">
        <Icon name="hugeicons:add-01" class="size-4 shrink-0" />
        <span>Add Banner</span>
      </Button>
    </div>

    <p class="text-muted-foreground -mt-2 text-sm tracking-tight">
      Banners shown on the event website, grouped by placement. Drag to reorder within a group.
    </p>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <Spinner class="size-6" />
    </div>

    <!-- Empty -->
    <div
      v-else-if="!totalCount"
      class="flex flex-col items-center justify-center gap-y-4 py-16 text-center"
    >
      <div
        class="*:bg-background/80 text-muted-foreground flex items-center *:rounded-lg *:border *:p-3 [&_svg]:size-5"
      >
        <div><Icon name="hugeicons:carousel-horizontal-02" /></div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tight">No banners yet</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          Add a banner to promote announcements or paid ads on the event website.
        </p>
      </div>
      <Button v-if="canCreate" size="sm" class="mt-2" @click="openCreate">
        <Icon name="hugeicons:add-01" class="size-4 shrink-0" />
        <span>Add your first banner</span>
      </Button>
    </div>

    <!-- Grouped by placement -->
    <Lightbox v-else :items="lightboxItems" :alt="''" :show-thumbnails="lightboxItems.length > 1">
      <template #trigger="{ openAt }">
        <div class="flex flex-col gap-y-4">
          <BannerPlacementGroup
            v-for="group in groups"
            :key="group.placement"
            :group="group"
            :can-update="canUpdate"
            :can-delete="canDelete"
            :open-image="(banner) => openAt(lightboxIndexFor(banner))"
            @toggle="toggleActive"
            @edit="openEdit"
            @delete="confirmDelete"
            @analytics="openAnalytics"
            @reorder="saveGroupOrder"
          />
        </div>
      </template>
    </Lightbox>

    <!-- Form dialog -->
    <FormBanner
      v-model:open="formOpen"
      :project-username="username"
      :banner="editingBanner"
      @success="fetchBanners"
    />

    <!-- Delete confirmation -->
    <DialogResponsive v-model:open="deleteOpen" dialog-max-width="22rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Delete this banner?</h3>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            "{{ deletingBanner?.title || "Untitled banner" }}" will be removed from the website.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" size="sm" :disabled="deleting" @click="deleteOpen = false">
              Cancel
            </Button>
            <Button variant="destructive" size="sm" :disabled="deleting" @click="handleDelete">
              <Spinner v-if="deleting" class="size-4" />
              Delete
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Analytics dialog -->
    <DialogResponsive
      v-model:open="analyticsOpen"
      dialog-max-width="32rem"
      :overflow-content="true"
    >
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Banner analytics</h3>
          <p class="text-muted-foreground mt-1 truncate text-sm tracking-tight">
            {{ analyticsBanner?.title || "Untitled banner" }} · last 14 days
          </p>

          <div v-if="analyticsLoading" class="flex justify-center py-12">
            <Spinner class="size-6" />
          </div>

          <template v-else-if="analytics">
            <div class="mt-4 grid grid-cols-3 gap-2">
              <div class="border-border rounded-lg border p-3">
                <p class="text-muted-foreground text-xs tracking-tight">Impressions</p>
                <p class="mt-1 text-xl font-semibold tracking-tighter tabular-nums">
                  {{ formatCount(analytics.summary.impressions) }}
                </p>
              </div>
              <div class="border-border rounded-lg border p-3">
                <p class="text-muted-foreground text-xs tracking-tight">Clicks</p>
                <p class="mt-1 text-xl font-semibold tracking-tighter tabular-nums">
                  {{ formatCount(analytics.summary.clicks) }}
                </p>
              </div>
              <div class="border-border rounded-lg border p-3">
                <p class="text-muted-foreground text-xs tracking-tight">CTR</p>
                <p class="mt-1 text-xl font-semibold tracking-tighter tabular-nums">
                  {{ analytics.summary.ctr }}%
                </p>
              </div>
            </div>

            <div class="mt-5 space-y-1.5">
              <div
                class="text-muted-foreground flex items-center justify-between text-xs tracking-tight"
              >
                <span>Daily trend</span>
                <span class="flex items-center gap-3">
                  <span class="flex items-center gap-1">
                    <span class="bg-muted-foreground/30 size-2 rounded-full" />Impressions
                  </span>
                  <span class="flex items-center gap-1">
                    <span class="bg-primary size-2 rounded-full" />Clicks
                  </span>
                </span>
              </div>
              <div class="flex items-end gap-1" style="height: 80px">
                <div
                  v-for="d in analytics.per_day"
                  :key="d.date"
                  class="bg-muted/40 relative flex-1 rounded-sm"
                  style="height: 80px"
                  v-tippy="
                    `${$dayjs(d.date).format('MMM D, YYYY')}: ${d.impressions} impressions · ${d.clicks} clicks`
                  "
                >
                  <div
                    class="bg-muted-foreground/30 absolute inset-x-0 bottom-0 rounded-sm"
                    :style="{ height: barPct(d.impressions) }"
                  />
                  <div
                    class="bg-primary absolute inset-x-0 bottom-0 rounded-sm"
                    :style="{ height: barPct(d.clicks) }"
                  />
                </div>
              </div>
            </div>
          </template>

          <div v-else class="text-muted-foreground py-12 text-center text-sm tracking-tight">
            No analytics data yet.
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import BannerPlacementGroup from "@/components/project/BannerPlacementGroup.vue";
import FormBanner from "@/components/project/FormBanner.vue";
import { Lightbox } from "@/components/ui/lightbox";
import { toast } from "vue-sonner";

const props = defineProps({ project: Object });

usePageMeta(null, {
  title: computed(() => `Banners · ${props.project?.name || "Project"}`),
});

const route = useRoute();
const client = useSanctumClient();
const { $dayjs } = useNuxtApp();
const { username } = route.params;
const apiBase = `/api/projects/${username}/banners`;

const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("banners.create"));
const canUpdate = computed(() => hasPermission("banners.update"));
const canDelete = computed(() => hasPermission("banners.delete"));

// ── Placement grouping ────────────────────────────────
// Known placements render first, in this order; each gets its own frame.
const PLACEMENTS = [
  {
    placement: "hero",
    label: "Hero",
    description: "Carousel at the top of the event website home page.",
  },
  {
    placement: "visitor-cta",
    label: "Visitor CTA",
    description: 'Cross-promo cards in the "visit our other events" section.',
  },
  {
    placement: "hero-announcement",
    label: "Hero Announcement",
    description: "Rotating text strip above the hero title on the event website.",
  },
];

const humanize = (value) => value.replace(/[-_]+/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());

const groups = ref([]);
const loading = ref(true);

const buildGroups = (list) => {
  const byPlacement = new Map();
  for (const banner of list) {
    const key = banner.placement || "hero";
    if (!byPlacement.has(key)) byPlacement.set(key, []);
    byPlacement.get(key).push(banner);
  }

  const result = [];
  for (const meta of PLACEMENTS) {
    const items = byPlacement.get(meta.placement);
    if (items?.length) {
      result.push({ ...meta, items });
      byPlacement.delete(meta.placement);
    }
  }
  // Surface any unexpected placement value so its banners stay visible/manageable.
  for (const [placement, items] of byPlacement) {
    result.push({ placement, label: humanize(placement), description: "", items });
  }
  return result;
};

const totalCount = computed(() =>
  groups.value.reduce((sum, group) => sum + group.items.length, 0)
);

// ── Lightbox ──────────────────────────────────────────
// One lightbox over every image-bearing banner, in grouped display order so the
// index a group emits matches the lightbox slide.
const orderedImageBanners = computed(() =>
  groups.value.flatMap((group) => group.items).filter((banner) => banner.image)
);

const lightboxItems = computed(() =>
  orderedImageBanners.value.map((banner) => ({
    ...banner.image,
    alt: banner.title || banner.image.alt || "Banner",
    caption: banner.settings?.caption || banner.image.caption || "",
  }))
);

const lightboxIndexFor = (banner) =>
  orderedImageBanners.value.findIndex((item) => item.id === banner.id);

const fetchBanners = async () => {
  try {
    loading.value = true;
    const response = await client(apiBase);
    groups.value = buildGroups(response.data ?? []);
  } catch (err) {
    console.error("Failed to load banners:", err);
    toast.error("Failed to load banners");
  } finally {
    loading.value = false;
  }
};

onMounted(fetchBanners);

// ── Form ──────────────────────────────────────────────
const formOpen = ref(false);
const editingBanner = ref(null);

const openCreate = () => {
  editingBanner.value = null;
  formOpen.value = true;
};
const openEdit = (banner) => {
  editingBanner.value = banner;
  formOpen.value = true;
};

// ── Analytics ─────────────────────────────────────────
const formatCount = (n) => (n ?? 0).toLocaleString();

const analyticsOpen = ref(false);
const analyticsBanner = ref(null);
const analytics = ref(null);
const analyticsLoading = ref(false);

const analyticsMax = computed(() =>
  Math.max(1, ...(analytics.value?.per_day || []).map((d) => d.impressions))
);
const barPct = (v) => `${Math.round((v / analyticsMax.value) * 100)}%`;

const openAnalytics = async (banner) => {
  analyticsBanner.value = banner;
  analytics.value = null;
  analyticsOpen.value = true;
  analyticsLoading.value = true;
  try {
    const response = await client(`${apiBase}/${banner.id}/analytics`);
    analytics.value = response.data;
  } catch (err) {
    toast.error("Failed to load analytics");
  } finally {
    analyticsLoading.value = false;
  }
};

// ── Toggle ────────────────────────────────────────────
const toggleActive = async (banner) => {
  const previous = banner.is_active;
  banner.is_active = !previous;
  try {
    await client(`${apiBase}/${banner.id}/toggle`, { method: "PATCH" });
  } catch (err) {
    banner.is_active = previous;
    toast.error("Failed to update status");
  }
};

// ── Reorder (within a single placement) ───────────────
const saveGroupOrder = async ({ placement, ids }) => {
  // Mirror the new order into our own state so the lightbox index stays correct.
  const group = groups.value.find((item) => item.placement === placement);
  if (group) {
    const byId = new Map(group.items.map((banner) => [banner.id, banner]));
    group.items = ids.map((id) => byId.get(id)).filter(Boolean);
  }

  try {
    await client(`${apiBase}/reorder`, {
      method: "POST",
      body: { media_ids: ids },
    });
  } catch (err) {
    toast.error("Failed to save order");
    fetchBanners();
  }
};

// ── Delete ────────────────────────────────────────────
const deleteOpen = ref(false);
const deletingBanner = ref(null);
const deleting = ref(false);

const confirmDelete = (banner) => {
  deletingBanner.value = banner;
  deleteOpen.value = true;
};

// Drop the banner from its group, then drop the group if it's now empty.
const removeBannerFromGroups = (id) => {
  for (const group of groups.value) {
    const index = group.items.findIndex((banner) => banner.id === id);
    if (index !== -1) {
      group.items.splice(index, 1);
      break;
    }
  }
  groups.value = groups.value.filter((group) => group.items.length);
};

const handleDelete = async () => {
  if (!deletingBanner.value) return;
  deleting.value = true;
  try {
    await client(`${apiBase}/bulk-delete`, {
      method: "DELETE",
      body: { media_ids: [deletingBanner.value.id] },
    });
    removeBannerFromGroups(deletingBanner.value.id);
    toast.success("Banner deleted");
    deleteOpen.value = false;
  } catch (err) {
    toast.error("Failed to delete banner");
  } finally {
    deleting.value = false;
  }
};
</script>
