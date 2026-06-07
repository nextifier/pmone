<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-5xl xl:max-w-6xl">
    <!-- Page header -->
    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:image-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Gallery</h1>
        <span
          v-if="!loading && gallery.length"
          class="text-muted-foreground text-sm tracking-tight tabular-nums"
        >
          {{ gallery.length }}
        </span>
      </div>
    </div>

    <!-- Uploader -->
    <div class="space-y-2">
      <InputFile
        ref="uploader"
        v-model="pendingFiles"
        allow-multiple
        :max-files="50"
        max-file-size="20MB"
        :accepted-file-types="['image/jpeg', 'image/png', 'image/webp']"
      />
      <p class="text-muted-foreground text-xs tracking-tight">
        Photos keep their original aspect ratio. Up to 50 files, max 20MB each.
        <span v-if="uploading" class="text-foreground">Uploading…</span>
      </p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <Spinner class="size-6" />
    </div>

    <!-- Empty -->
    <div
      v-else-if="!gallery.length"
      class="flex flex-col items-center justify-center gap-y-4 py-12 text-center"
    >
      <div
        class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
      >
        <div class="translate-y-1.5 -rotate-6">
          <Icon name="hugeicons:image-01" />
        </div>
        <div>
          <Icon name="hugeicons:layers-01" />
        </div>
        <div class="translate-y-1.5 rotate-6">
          <Icon name="hugeicons:folder-add" />
        </div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tight">No photos yet</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          Drag photos above to build the event gallery shown on the website.
        </p>
      </div>
    </div>

    <!-- Gallery manager (reorder / select / bulk delete / single delete / lightbox).
         GalleryManager updates v-model:items in place + persists in the background,
         so no @changed refetch is needed (that caused a full reload on each reorder). -->
    <GalleryManager
      v-else
      v-model:items="gallery"
      :alt="event?.title || 'Gallery'"
      thumbnail-key="sm"
    />
  </div>
</template>

<script setup>
import GalleryManager from "@/components/GalleryManager.vue";
import InputFile from "@/components/InputFile.vue";
import { Spinner } from "@/components/ui/spinner";
import { useDebounceFn } from "@vueuse/core";
import { toast } from "vue-sonner";

const props = defineProps({ event: Object, project: Object });

usePageMeta(null, { title: "Gallery" });

const route = useRoute();
const client = useSanctumClient();
const { username, eventSlug } = route.params;
const apiBase = `/api/projects/${username}/events/${eventSlug}/gallery`;

const gallery = ref([]);
const loading = ref(true);

const fetchGallery = async () => {
  try {
    loading.value = true;
    const response = await client(apiBase);
    gallery.value = response.data ?? [];
  } catch (err) {
    console.error("Failed to load gallery:", err);
    toast.error("Failed to load gallery");
  } finally {
    loading.value = false;
  }
};

onMounted(fetchGallery);

// --- Upload: FilePond auto-uploads to temp; once the batch settles, attach the
// temp files to the event gallery, then clear the uploader + refresh.
const uploader = ref(null);
const pendingFiles = ref([]);
const uploading = ref(false);

const flushUpload = useDebounceFn(async () => {
  const ids = (pendingFiles.value || []).filter(
    (f) => typeof f === "string" && f.startsWith("tmp-")
  );
  if (!ids.length || uploading.value) return;

  uploading.value = true;
  try {
    const response = await client(apiBase, { method: "POST", body: { files: ids } });
    toast.success(response.message || "Photos uploaded");
    pendingFiles.value = [];
    uploader.value?.pond?.removeFiles?.();
    await fetchGallery();
  } catch (err) {
    toast.error("Failed to upload photos", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    uploading.value = false;
  }
}, 700);

watch(pendingFiles, (val) => {
  if (val?.length) flushUpload();
});
</script>
