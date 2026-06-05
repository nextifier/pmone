<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-4xl">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:image-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Banners</h1>
        <span
          v-if="!loading && banners.length"
          class="text-muted-foreground text-sm tracking-tight tabular-nums"
        >
          {{ banners.length }}
        </span>
      </div>

      <Button v-if="canCreate" size="sm" @click="openCreate">
        <Icon name="hugeicons:add-01" class="size-4 shrink-0" />
        <span>Add Banner</span>
      </Button>
    </div>

    <p class="text-muted-foreground -mt-2 text-sm tracking-tight">
      Banners shown in the hero carousel on the event website. Drag to reorder.
    </p>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <Spinner class="size-6" />
    </div>

    <!-- Empty -->
    <div
      v-else-if="!banners.length"
      class="flex flex-col items-center justify-center gap-y-4 py-16 text-center"
    >
      <div
        class="*:bg-background/80 text-muted-foreground flex items-center *:rounded-lg *:border *:p-3 [&_svg]:size-5"
      >
        <div><Icon name="hugeicons:image-02" /></div>
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

    <!-- List -->
    <div v-else ref="listRef" class="flex flex-col gap-y-2">
      <div
        v-for="banner in banners"
        :key="banner.id"
        class="border-border flex items-center gap-3 rounded-lg border p-3 transition-colors"
        :class="{ 'opacity-60': !banner.is_active }"
      >
        <!-- Drag handle -->
        <div v-if="canUpdate" class="flex shrink-0 items-center">
          <Icon
            name="lucide:grip-vertical"
            class="drag-handle text-muted-foreground size-4 cursor-grab"
          />
        </div>

        <!-- Thumbnail -->
        <div class="bg-muted aspect-video w-20 shrink-0 overflow-hidden rounded-md sm:w-24">
          <img
            v-if="banner.image?.sm"
            :src="banner.image.sm"
            :alt="banner.title || 'Banner'"
            class="size-full object-cover"
            loading="lazy"
          />
          <div
            v-else
            class="text-muted-foreground flex size-full items-center justify-center"
          >
            <Icon
              :name="banner.type === 'text' ? 'lucide:type' : 'lucide:image'"
              class="size-5"
            />
          </div>
        </div>

        <!-- Info -->
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-x-2">
            <p class="truncate text-sm font-medium tracking-tight sm:text-base">
              {{ banner.title || "Untitled banner" }}
            </p>
            <Badge variant="outline" class="shrink-0">{{ typeLabel(banner.type) }}</Badge>
            <Badge v-if="!banner.is_active" variant="muted" class="shrink-0">Hidden</Badge>
          </div>
          <p
            v-if="scheduleLabel(banner)"
            class="text-muted-foreground mt-0.5 flex items-center gap-x-1 truncate text-xs tracking-tight sm:text-sm"
          >
            <Icon name="lucide:calendar" class="size-3.5 shrink-0" />
            {{ scheduleLabel(banner) }}
          </p>
        </div>

        <!-- Stats -->
        <div
          class="text-muted-foreground hidden shrink-0 items-center gap-3 text-sm tracking-tight tabular-nums lg:flex"
        >
          <span class="flex items-center gap-1" v-tippy="'Impressions'">
            <Icon name="lucide:eye" class="size-4" />{{ formatCount(banner.impressions_count) }}
          </span>
          <span class="flex items-center gap-1" v-tippy="'Clicks'">
            <Icon name="lucide:mouse-pointer-click" class="size-4" />{{ formatCount(banner.clicks_count) }}
          </span>
        </div>

        <!-- Actions -->
        <div class="flex shrink-0 items-center gap-2">
          <button
            type="button"
            class="hover:bg-muted rounded-md p-1.5"
            aria-label="Analytics"
            @click="openAnalytics(banner)"
          >
            <Icon name="lucide:chart-no-axes-column" class="size-4" />
          </button>
          <Switch
            v-if="canUpdate"
            :model-value="banner.is_active"
            @update:model-value="toggleActive(banner)"
          />
          <button
            v-if="canUpdate"
            type="button"
            class="hover:bg-muted rounded-md p-1.5"
            aria-label="Edit banner"
            @click="openEdit(banner)"
          >
            <Icon name="lucide:pencil-line" class="size-4" />
          </button>
          <button
            v-if="canDelete"
            type="button"
            class="hover:bg-destructive/10 rounded-md p-1.5"
            aria-label="Delete banner"
            @click="confirmDelete(banner)"
          >
            <Icon name="lucide:trash" class="text-destructive size-4" />
          </button>
        </div>
      </div>
    </div>

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
    <DialogResponsive v-model:open="analyticsOpen" dialog-max-width="32rem" :overflow-content="true">
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
                    <span class="bg-muted-foreground/30 size-2 rounded-full" />Impr.
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
                  v-tippy="`${d.date}: ${d.impressions} impr · ${d.clicks} clicks`"
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
import FormBanner from "@/components/project/FormBanner.vue";
import { useSortableList } from "@/composables/useSortableList";
import { toast } from "vue-sonner";

const props = defineProps({ project: Object });

usePageMeta(null, {
  title: computed(() => `Banners · ${props.project?.name || "Project"}`),
});

const route = useRoute();
const client = useSanctumClient();
const { username } = route.params;
const apiBase = `/api/projects/${username}/banners`;

const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("banners.create"));
const canUpdate = computed(() => hasPermission("banners.update"));
const canDelete = computed(() => hasPermission("banners.delete"));

const banners = ref([]);
const loading = ref(true);

const fetchBanners = async () => {
  try {
    loading.value = true;
    const response = await client(apiBase);
    banners.value = response.data ?? [];
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
  Math.max(1, ...(analytics.value?.per_day || []).map((d) => d.impressions)),
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

// ── Type / schedule labels ────────────────────────────
const typeLabel = (type) =>
  ({ image: "Image", text: "Text", image_text: "Image + Text" })[type] ?? type;

const fmtDate = (iso) =>
  new Date(iso).toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" });

const scheduleLabel = (banner) => {
  if (banner.start_time && banner.end_time) {
    return `${fmtDate(banner.start_time)} – ${fmtDate(banner.end_time)}`;
  }
  if (banner.start_time) return `From ${fmtDate(banner.start_time)}`;
  if (banner.end_time) return `Until ${fmtDate(banner.end_time)}`;
  return null;
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

// ── Reorder ───────────────────────────────────────────
const listRef = ref(null);
useSortableList(listRef, banners, {
  enabled: canUpdate,
  onReorder: async () => {
    try {
      await client(`${apiBase}/reorder`, {
        method: "POST",
        body: { media_ids: banners.value.map((b) => b.id) },
      });
    } catch (err) {
      toast.error("Failed to save order");
      fetchBanners();
    }
  },
});

// ── Delete ────────────────────────────────────────────
const deleteOpen = ref(false);
const deletingBanner = ref(null);
const deleting = ref(false);

const confirmDelete = (banner) => {
  deletingBanner.value = banner;
  deleteOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingBanner.value) return;
  deleting.value = true;
  try {
    await client(`${apiBase}/bulk-delete`, {
      method: "DELETE",
      body: { media_ids: [deletingBanner.value.id] },
    });
    banners.value = banners.value.filter((b) => b.id !== deletingBanner.value.id);
    toast.success("Banner deleted");
    deleteOpen.value = false;
  } catch (err) {
    toast.error("Failed to delete banner");
  } finally {
    deleting.value = false;
  }
};
</script>
