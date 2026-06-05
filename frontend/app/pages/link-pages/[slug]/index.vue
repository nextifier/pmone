<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <!-- Back button -->
    <ButtonBack destination="/link-pages" :forceDestination="true" />

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div v-if="linkPage">
        <h1 class="text-lg font-semibold tracking-tighter sm:text-xl">{{ linkPage.title }}</h1>
        <div class="flex items-center gap-x-0.5">
          <span class="text-muted-foreground text-xs tracking-tight sm:text-sm"
            >{{ domain }}/{{ linkPage.slug }}</span
          >
          <ButtonCopy :text="publicUrl" />
        </div>
      </div>
      <div v-else class="space-y-1">
        <div class="bg-muted h-5 w-40 animate-pulse rounded" />
        <div class="bg-muted h-4 w-24 animate-pulse rounded" />
      </div>

      <div class="ml-auto flex items-center gap-1 sm:gap-2">
        <a
          v-if="linkPage"
          :href="publicUrl"
          target="_blank"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="lucide:external-link" class="size-4 shrink-0" />
          <span>Preview</span>
        </a>
        <nuxt-link
          v-if="linkPage"
          :to="`/link-pages/${linkPage.slug}/analytics`"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="lucide:chart-no-axes-combined" class="size-4 shrink-0" />
          <span>Analytics</span>
        </nuxt-link>
        <nuxt-link
          v-if="linkPage"
          :to="`/link-pages/${linkPage.slug}/trash`"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          <span>Trash</span>
        </nuxt-link>
        <Button size="sm" @click="openItemForm()">
          <Icon name="hugeicons:add-01" class="size-4" />
          New Item
          <KbdGroup>
            <Kbd>N</Kbd>
          </KbdGroup>
        </Button>
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 3" :key="i" class="bg-muted h-20 animate-pulse rounded-lg" />
    </div>

    <!-- Empty state -->
    <div v-else-if="items.length === 0" class="flex flex-col items-center justify-center py-16">
      <Icon name="hugeicons:link-01" class="text-muted-foreground size-12" />
      <p class="text-muted-foreground mt-3 text-sm tracking-tight">No items yet</p>
      <Button size="sm" class="mt-4" @click="openItemForm()">
        <Icon name="hugeicons:add-01" class="size-4" />
        Add your first item
      </Button>
    </div>

    <!-- Items list with drag and drop -->
    <div v-else ref="sortableEl" class="space-y-2">
      <LinkPageItemCard
        v-for="item in items"
        :key="item.id"
        :item="item"
        @edit="openItemForm(item)"
        @delete="handleDeleteItem(item)"
        @toggle="handleToggleItem(item)"
      />
    </div>

    <!-- Banners -->
    <section class="space-y-3 rounded-xl border p-4 sm:p-5">
      <div class="space-y-1">
        <div class="flex items-center gap-2">
          <h2 class="text-base font-medium tracking-tighter sm:text-lg">Banners</h2>
          <span
            v-if="bannerTiles.length"
            class="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-xs font-medium tabular-nums"
          >
            {{ bannerTiles.length }}
          </span>
        </div>
        <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          Promotional images shown at the top of the public page. 16:9 ratio.
        </p>
      </div>

      <!-- Existing banners -->
      <GalleryManager
        v-if="bannerTiles.length"
        v-model:items="bannerTiles"
        :reorder-endpoint="bannerReorderEndpoint"
        :delete-endpoint="bannerDeleteEndpoint"
        alt="Banner"
        tile-aspect-class="aspect-video"
        columns-class="grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3"
        compact-toolbar
        @changed="fetchLinkPage"
      >
        <template #tile-overlay="{ item }">
          <span
            v-if="!item._banner.is_active"
            class="bg-background/85 text-muted-foreground absolute top-1.5 left-9 z-10 rounded-md px-1.5 py-0.5 text-xs font-medium tracking-tight backdrop-blur-sm"
          >
            Hidden
          </span>

          <div
            class="pointer-events-none absolute inset-x-0 bottom-0 flex items-center gap-1.5 bg-linear-to-t from-black/65 to-transparent px-2.5 pt-8 pr-10 pb-2"
          >
            <Icon
              v-if="item._banner.url"
              name="lucide:link"
              class="size-3.5 shrink-0 text-white/80"
            />
            <span class="truncate text-xs font-medium tracking-tight text-white/95 sm:text-sm">
              {{ item._banner.caption || "Untitled banner" }}
            </span>
          </div>

          <button
            type="button"
            class="bg-background/85 text-foreground hover:bg-background absolute right-1.5 bottom-1.5 z-10 flex size-7 items-center justify-center rounded-md shadow-sm backdrop-blur-sm transition active:scale-95"
            aria-label="Edit banner"
            @click.stop="openBannerForm(item._banner)"
          >
            <Icon name="lucide:pencil-line" class="size-4 shrink-0" />
          </button>
        </template>
      </GalleryManager>

      <!-- Upload -->
      <InputFile
        ref="bannerInputRef"
        v-model="bannerFiles"
        allow-multiple
        :max-files="20"
        :max-file-size="'20MB'"
        :accepted-file-types="['image/jpeg', 'image/png', 'image/webp']"
      />

      <div v-if="pendingBannerFolders.length" class="flex justify-end">
        <Button size="sm" :disabled="uploadingBanners" @click="uploadBanners">
          <Spinner v-if="uploadingBanners" class="size-4" />
          <Icon v-else name="hugeicons:add-01" class="size-4" />
          Upload {{ pendingBannerFolders.length }} banner{{
            pendingBannerFolders.length > 1 ? "s" : ""
          }}
        </Button>
      </div>
    </section>

    <!-- Form dialog -->
    <FormLinkPageItem
      v-model:open="itemFormOpen"
      :link-page-slug="slug"
      :item="editingItem"
      @success="fetchLinkPage"
    />

    <FormLinkPageBanner
      v-model:open="bannerFormOpen"
      :link-page-slug="slug"
      :banner="editingBanner"
      @success="fetchLinkPage"
    />
  </div>
</template>

<script setup>
import LinkPageItemCard from "@/components/link-page/LinkPageItemCard.vue";
import FormLinkPageItem from "@/components/link-page/FormLinkPageItem.vue";
import FormLinkPageBanner from "@/components/link-page/FormLinkPageBanner.vue";
import { useSortableList } from "@/composables/useSortableList";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["link_pages.read"],
  layout: "app",
});

const route = useRoute();
const slug = computed(() => route.params.slug);

const config = useRuntimeConfig();
const client = useSanctumClient();
const loading = ref(true);
const linkPage = ref(null);
const items = ref([]);

// Banners
const bannerTiles = ref([]);
const bannerFiles = ref([]);
const bannerInputRef = ref(null);
const uploadingBanners = ref(false);
const bannerFormOpen = ref(false);
const editingBanner = ref(null);

const bannerReorderEndpoint = computed(() => `/api/link-pages/${slug.value}/banners/reorder`);
const bannerDeleteEndpoint = computed(() => `/api/link-pages/${slug.value}/banners/bulk-delete`);

const pendingBannerFolders = computed(() =>
  (bannerFiles.value || []).filter((f) => typeof f === "string" && f.startsWith("tmp-"))
);

usePageMeta(null, {
  title: computed(() => (linkPage.value?.title ? `${linkPage.value.title} · Link Pages` : "Link Page")),
});

const domain = computed(() => config.public.siteUrl.replace(/^https?:\/\//, ""));
const publicUrl = computed(() => {
  return `${config.public.siteUrl}/${linkPage.value?.slug}`;
});

const fetchLinkPage = async () => {
  try {
    const response = await client(`/api/link-pages/${slug.value}`);
    linkPage.value = response.data;
    items.value = response.data.items || [];
    bannerTiles.value = (response.data.banners || []).map((banner) => ({
      id: banner.id,
      lqip: banner.image?.lqip,
      sm: banner.image?.sm,
      md: banner.image?.md,
      lg: banner.image?.lg,
      url: banner.image?.url,
      name: banner.caption || "Banner",
      _banner: banner,
    }));
  } catch (err) {
    console.error("Failed to fetch link page:", err);
    toast.error("Failed to load link page");
  } finally {
    loading.value = false;
  }
};

const uploadBanners = async () => {
  const folders = pendingBannerFolders.value;
  if (!folders.length) return;

  uploadingBanners.value = true;
  try {
    await client(`/api/link-pages/${slug.value}/banners`, {
      method: "POST",
      body: { tmp_images: folders },
    });
    bannerInputRef.value?.pond?.removeFiles?.();
    bannerFiles.value = [];
    toast.success(`${folders.length} banner${folders.length > 1 ? "s" : ""} added`);
    await fetchLinkPage();
  } catch (err) {
    toast.error(err.response?._data?.message || err.message || "Failed to upload banners");
  } finally {
    uploadingBanners.value = false;
  }
};

const openBannerForm = (banner) => {
  editingBanner.value = banner;
  bannerFormOpen.value = true;
};

onMounted(fetchLinkPage);

// Sortable
const sortableEl = ref(null);

const updateOrder = async () => {
  const orders = items.value.map((item, index) => ({
    id: item.id,
    order: index,
  }));

  try {
    await client(`/api/link-pages/${slug.value}/items/reorder`, {
      method: "PUT",
      body: { orders },
    });
  } catch (err) {
    console.error("Failed to reorder:", err);
    toast.error("Failed to reorder items");
    await fetchLinkPage();
  }
};

useSortableList(sortableEl, items, { onReorder: updateOrder });

// Item form
const itemFormOpen = ref(false);
const editingItem = ref(null);

const openItemForm = (item = null) => {
  editingItem.value = item;
  itemFormOpen.value = true;
};

// Toggle active
const handleToggleItem = async (item) => {
  const original = item.is_active;
  item.is_active = !item.is_active;

  try {
    await client(`/api/link-pages/${slug.value}/items/${item.id}/toggle`, {
      method: "PATCH",
    });
    toast.success(`Item ${item.is_active ? "activated" : "deactivated"}`);
  } catch (err) {
    item.is_active = original;
    toast.error("Failed to update item status");
  }
};

// Delete item
const handleDeleteItem = async (item) => {
  try {
    await client(`/api/link-pages/${slug.value}/items/${item.id}`, {
      method: "DELETE",
    });
    items.value = items.value.filter((i) => i.id !== item.id);
    toast.success("Item deleted");
  } catch (err) {
    toast.error("Failed to delete item");
  }
};

// Keyboard shortcut
const isPageActive = ref(true);
onActivated(() => {
  isPageActive.value = true;
});
onDeactivated(() => {
  isPageActive.value = false;
});

defineShortcuts({
  n: {
    handler: () => openItemForm(),
    whenever: [isPageActive],
  },
});
</script>
