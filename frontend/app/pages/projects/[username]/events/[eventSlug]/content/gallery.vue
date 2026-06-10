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
        allow-multiple
        :max-files="100"
        :max-parallel-uploads="3"
        max-file-size="20MB"
        :accepted-file-types="['image/jpeg', 'image/png', 'image/webp']"
        @complete="onUploadComplete"
        @progress="onProgress"
      />
      <p class="text-muted-foreground text-xs tracking-tight">
        Photos keep their original aspect ratio. Up to 100 files, max 20MB each.
        <span v-if="uploadLabel" class="text-foreground">{{ uploadLabel }}</span>
      </p>
    </div>

    <!-- Drop zone: drag photos anywhere over the grid (or paste from clipboard)
         to upload, in addition to the FilePond area above. -->
    <div
      class="relative"
      @dragenter.prevent="onDragEnter"
      @dragover.prevent
      @dragleave="onDragLeave"
      @drop.prevent="onDrop"
    >
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
            Drag photos here or paste from your clipboard to build the gallery.
          </p>
        </div>
      </div>

      <!-- Gallery manager (reorder / select / bulk delete / single delete / lightbox /
           caption editing). Updates v-model:items in place + persists in the background. -->
      <GalleryManager
        v-else
        v-model:items="gallery"
        :alt="event?.title || 'Gallery'"
        thumbnail-key="sm"
        editable-caption
      />

      <!-- Drag overlay -->
      <div
        v-if="isDragging"
        class="border-primary bg-background/80 text-primary pointer-events-none absolute inset-0 z-20 flex flex-col items-center justify-center gap-y-2 rounded-xl border-2 border-dashed backdrop-blur-sm"
      >
        <Icon name="hugeicons:image-add-02" class="size-7" />
        <p class="font-medium tracking-tight">Drop photos to upload</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import GalleryManager from "@/components/GalleryManager.vue";
import InputFile from "@/components/InputFile.vue";
import { Spinner } from "@/components/ui/spinner";
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

// --- Upload flow ---------------------------------------------------------
// FilePond auto-uploads each dropped file to temp storage. We wait for the
// WHOLE batch to finish (InputFile @complete fires on FilePond's `processfiles`)
// before attaching — attaching per file mid-upload used to abort the remaining
// uploads. The temp ids are attached in chunks (the store endpoint caps each
// request at 50) so large batches stay fast and update the grid progressively.
const ATTACH_CHUNK_SIZE = 20;

const uploader = ref(null);
const uploading = ref(false);
const receiving = reactive({ done: 0, total: 0 });
const saving = reactive({ done: 0, total: 0 });

const uploadLabel = computed(() => {
  if (uploading.value) {
    return saving.total ? `Saving ${saving.done}/${saving.total}…` : "Saving photos…";
  }
  if (receiving.total > 0 && receiving.done < receiving.total) {
    return `Uploading ${receiving.done}/${receiving.total}…`;
  }
  return "";
});

const onProgress = ({ done, total }) => {
  receiving.done = done ?? 0;
  receiving.total = total ?? 0;
};

const onUploadComplete = async (ids) => {
  const tmpIds = (ids || []).filter(
    (f) => typeof f === "string" && f.startsWith("tmp-")
  );
  if (!tmpIds.length || uploading.value) return;

  uploading.value = true;
  saving.done = 0;
  saving.total = tmpIds.length;
  let added = 0;

  try {
    for (let i = 0; i < tmpIds.length; i += ATTACH_CHUNK_SIZE) {
      const chunk = tmpIds.slice(i, i + ATTACH_CHUNK_SIZE);
      const response = await client(apiBase, {
        method: "POST",
        body: { files: chunk },
      });
      added += response?.added_count ?? 0;
      if (response?.data) gallery.value = response.data;
      saving.done = Math.min(i + chunk.length, tmpIds.length);
    }

    if (added < tmpIds.length) {
      toast.warning(`${added} of ${tmpIds.length} photos uploaded`, {
        description: "Some photos couldn't be processed. Try uploading the rest again.",
      });
    } else {
      toast.success(`${added} photo${added === 1 ? "" : "s"} uploaded`);
    }
  } catch (err) {
    toast.error("Failed to upload photos", {
      description: err?.data?.message || err?.message,
    });
    await fetchGallery();
  } finally {
    uploader.value?.clear?.();
    uploading.value = false;
    saving.done = 0;
    saving.total = 0;
  }
};

// --- Drag-onto-grid + paste-from-clipboard -------------------------------
// Both funnel into the same FilePond queue via the uploader's addFiles(), so
// they share the batch attach flow. A depth counter keeps the overlay stable
// while dragging across child elements.
const isDragging = ref(false);
let dragDepth = 0;

const onDragEnter = (event) => {
  if (![...(event.dataTransfer?.types || [])].includes("Files")) return;
  dragDepth += 1;
  isDragging.value = true;
};

const onDragLeave = () => {
  dragDepth = Math.max(0, dragDepth - 1);
  if (dragDepth === 0) isDragging.value = false;
};

const onDrop = (event) => {
  dragDepth = 0;
  isDragging.value = false;
  const files = event.dataTransfer?.files;
  if (files?.length) uploader.value?.addFiles?.(files);
};

const onPaste = (event) => {
  const files = [...(event.clipboardData?.files || [])].filter(
    (f) => f.type && f.type.startsWith("image/")
  );
  if (files.length) {
    event.preventDefault();
    uploader.value?.addFiles?.(files);
  }
};

onMounted(() => window.addEventListener("paste", onPaste));
onBeforeUnmount(() => window.removeEventListener("paste", onPaste));
</script>
