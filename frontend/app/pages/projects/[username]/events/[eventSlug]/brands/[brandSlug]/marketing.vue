<template>
  <div class="flex flex-col gap-y-6">
    <div class="flex items-start justify-between gap-x-2">
      <div class="space-y-1">
        <h3 class="page-title">Promotion Posts</h3>
        <p class="page-description">Marketing materials for this brand.</p>
      </div>
      <Button v-if="event?.can_edit" @click="showAdd = true" size="sm" class="shrink-0">
        <Icon name="hugeicons:add-01" class="size-4" />
        Add Post
      </Button>
    </div>

    <form v-if="event?.can_edit" @submit.prevent="saveLimit" class="flex items-end gap-2">
      <div class="flex-1 space-y-2">
        <Label for="promotion_post_limit">Post Limit</Label>
        <Input
          id="promotion_post_limit"
          v-model.number="postLimit"
          type="number"
          min="1"
          max="100"
          placeholder="1"
        />
      </div>
      <Button type="submit" :disabled="savingLimit" class="shrink-0">
        <Icon v-if="savingLimit" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
        Save
      </Button>
    </form>

    <div v-if="pending" class="flex items-center justify-center py-10">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <div v-else-if="posts?.length" class="flex flex-col gap-6">
      <div
        v-for="post in posts"
        :key="post.id"
        class="border-border overflow-hidden rounded-xl border"
      >
        <!-- Image grid -->
        <div
          v-if="getAllImages(post).length"
          class="grid gap-1 p-1"
          :class="getAllImages(post).length === 1 ? 'grid-cols-1' : 'grid-cols-2 sm:grid-cols-3'"
        >
          <div
            v-for="(img, idx) in getAllImages(post)"
            :key="idx"
            class="group relative aspect-square overflow-hidden rounded-lg"
          >
            <a
              :href="img.url || img.original"
              target="_blank"
              class="block size-full"
            >
              <img :src="img.md || img.url" class="size-full object-cover transition-opacity group-hover:opacity-90" />
            </a>
            <!-- Checkbox overlay -->
            <div
              class="absolute top-2 left-2 z-10 transition-opacity"
              :class="selectedImages[post.id]?.size ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
            >
              <Checkbox
                :model-value="selectedImages[post.id]?.has(img.id) || false"
                @update:model-value="toggleImageSelection(post.id, img.id)"
                class="!size-5 !rounded-md !border-white/80 !bg-black/30 !shadow-md data-[state=checked]:!bg-primary data-[state=checked]:!border-primary"
              />
            </div>
          </div>
        </div>

        <!-- Inline add images form -->
        <div v-if="addingImagesPostId === post.id" class="space-y-3 p-3">
          <p class="text-sm font-medium tracking-tight">Add Images</p>
          <InputFile
            ref="addImagesPondRef"
            v-model="addImageFiles"
            :accepted-file-types="['image/jpeg', 'image/png', 'image/jpg', 'image/webp']"
            :allow-multiple="true"
            :max-files="20"
          />
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            Max 20 images. JPG, PNG, or WebP.
          </p>
          <div class="flex gap-2">
            <Button
              size="sm"
              :disabled="savingAddImages || !addImageFiles.length"
              @click="saveAddImages(post.id)"
            >
              <Icon v-if="savingAddImages" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
              Upload
            </Button>
            <Button size="sm" variant="ghost" @click="cancelAddImages">
              Cancel
            </Button>
          </div>
        </div>

        <div class="p-3">
          <p class="text-xs tracking-tight sm:text-sm">{{ post.caption || "No caption" }}</p>
          <div class="mt-2.5 flex flex-wrap items-center gap-2">
            <Button
              v-if="post.caption"
              variant="outline"
              size="sm"
              @click="copyCaption(post.caption)"
            >
              <Icon :name="captionCopiedId === post.id ? 'lucide:check' : 'hugeicons:copy-01'" class="size-3.5" />
              {{ captionCopiedId === post.id ? 'Copied' : 'Copy Caption' }}
            </Button>
            <Button
              v-if="getAllImages(post).length"
              variant="outline"
              size="sm"
              :disabled="downloadingPostId === post.id"
              @click="downloadPostImages(post)"
            >
              <Icon :name="downloadingPostId === post.id ? 'svg-spinners:ring-resize' : 'lucide:download'" class="size-3.5" />
              {{ downloadLabel(post) }}
            </Button>
            <template v-if="event?.can_edit">
              <!-- Delete selected images -->
              <Button
                v-if="selectedImages[post.id]?.size"
                variant="ghost"
                size="sm"
                class="text-destructive"
                @click="confirmDeleteImages(post)"
              >
                <Icon name="hugeicons:delete-02" class="size-3.5" />
                Delete {{ selectedImages[post.id].size }} Image{{ selectedImages[post.id].size > 1 ? 's' : '' }}
              </Button>
              <!-- Add images to existing post -->
              <Button
                v-if="getAllImages(post).length < 20"
                variant="outline"
                size="sm"
                @click="startAddImages(post)"
              >
                <Icon name="hugeicons:image-add-01" class="size-3.5" />
                Add Images
              </Button>
              <!-- Delete all images -->
              <Button
                v-if="getAllImages(post).length && !selectedImages[post.id]?.size"
                variant="ghost"
                size="sm"
                class="text-muted-foreground"
                @click="confirmDeleteAllImages(post)"
              >
                <Icon name="hugeicons:delete-02" class="size-3.5" />
                Delete
              </Button>
              <!-- Delete post (when no images left) -->
              <Button
                v-if="!getAllImages(post).length"
                variant="ghost"
                size="sm"
                class="text-muted-foreground"
                @click="confirmDeletePost(post)"
              >
                <Icon name="hugeicons:delete-02" class="size-3.5" />
                Delete Post
              </Button>
            </template>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="flex flex-col items-center justify-center px-4 py-16">
      <div class="flex flex-col items-center justify-center gap-y-4 text-center">
        <div
          class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
        >
          <div class="translate-y-1.5 -rotate-6">
            <Icon name="hugeicons:image-02" />
          </div>
          <div>
            <Icon name="hugeicons:megaphone-02" />
          </div>
          <div class="translate-y-1.5 rotate-6">
            <Icon name="hugeicons:image-02" />
          </div>
        </div>

        <div class="space-y-2">
          <h1 class="text-2xl font-semibold tracking-tight">Promotion Posts</h1>
          <p class="text-muted-foreground max-w-md text-sm">
            No promotion posts yet. Add your first post to start marketing.
          </p>
        </div>
      </div>
    </div>

    <!-- Add Post dialog -->
    <DialogResponsive v-model:open="showAdd" dialog-max-width="500px">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-6">
          <div class="text-foreground text-lg font-medium tracking-tight">Add Post</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            Upload images and add a caption for this promotion post.
          </p>
          <form class="mt-4 space-y-4" @submit.prevent="createPost">
            <div class="space-y-2">
              <InputFile
                ref="newPostPondRef"
                v-model="newPostFiles"
                :accepted-file-types="['image/jpeg', 'image/png', 'image/jpg', 'image/webp']"
                :allow-multiple="true"
                :max-files="20"
              />
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Max 20 images. JPG, PNG, or WebP.
              </p>
            </div>
            <div class="space-y-2">
              <Label for="new_caption">Caption</Label>
              <Textarea
                id="new_caption"
                v-model="newCaption"
                rows="3"
                placeholder="Write a caption..."
              />
            </div>
            <div class="flex justify-end gap-2">
              <Button variant="outline" size="sm" type="button" @click="showAdd = false">
                Cancel
              </Button>
              <Button
                type="submit"
                size="sm"
                :disabled="creating || !newPostFiles.length"
              >
                <Icon v-if="creating" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                Upload Post
              </Button>
            </div>
          </form>
        </div>
      </template>
    </DialogResponsive>

    <!-- Delete confirmation dialog -->
    <DialogResponsive v-model:open="showDeleteDialog">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-6">
          <div class="text-foreground text-lg font-medium tracking-tight">
            {{ deleteDialogTitle }}
          </div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            {{ deleteDialogDescription }}
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" size="sm" @click="showDeleteDialog = false">
              Cancel
            </Button>
            <Button
              variant="destructive"
              size="sm"
              :disabled="deleting"
              @click="executeDelete"
            >
              <Icon v-if="deleting" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
              Delete
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";
import JSZip from "jszip";

const props = defineProps({ brandEvent: Object });
const emit = defineEmits(["refresh"]);
const route = useRoute();
const client = useSanctumClient();
const event = inject("event");
const showAdd = ref(false);
const newPostPondRef = ref(null);
const newPostFiles = ref([]);
const newCaption = ref("");
const creating = ref(false);
const postLimit = ref(props.brandEvent?.promotion_post_limit || 1);
const savingLimit = ref(false);

const brandUrl = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${route.params.brandSlug}`
);

async function saveLimit() {
  savingLimit.value = true;
  try {
    await client(brandUrl.value, {
      method: "PUT",
      body: { promotion_post_limit: postLimit.value },
    });
    toast.success("Post limit updated");
    emit("refresh");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to update");
  } finally {
    savingLimit.value = false;
  }
}

const apiUrl = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${route.params.brandSlug}/promotion-posts`
);
const data = ref(null);
const pending = ref(true);
const posts = computed(() => data.value?.data || []);
const captionCopiedId = ref(null);
const downloadingPostId = ref(null);

// Image selection state: { postId: Set<imageId> }
const selectedImages = ref({});

function getAllImages(post) {
  if (post.post_images?.length) return post.post_images;
  if (post.post_image) return [post.post_image];
  return [];
}

function toggleImageSelection(postId, imageId) {
  if (!selectedImages.value[postId]) {
    selectedImages.value[postId] = new Set();
  }
  const set = selectedImages.value[postId];
  if (set.has(imageId)) {
    set.delete(imageId);
    if (set.size === 0) {
      delete selectedImages.value[postId];
    }
  } else {
    set.add(imageId);
  }
  // Trigger reactivity
  selectedImages.value = { ...selectedImages.value };
}

function downloadLabel(post) {
  const selected = selectedImages.value[post.id];
  if (selected?.size) {
    return `Download ${selected.size} Image${selected.size > 1 ? 's' : ''}`;
  }
  return 'Download All Images';
}

async function copyCaption(caption) {
  try {
    await navigator.clipboard.writeText(caption);
    const post = posts.value.find((p) => p.caption === caption);
    if (post) {
      captionCopiedId.value = post.id;
      setTimeout(() => {
        captionCopiedId.value = null;
      }, 2000);
    }
  } catch {
    toast.error("Failed to copy");
  }
}

async function refresh() {
  pending.value = true;
  try {
    data.value = await client(apiUrl.value);
  } catch (e) {}
  pending.value = false;
}

onMounted(() => refresh());

async function createPost() {
  creating.value = true;
  try {
    const body = { caption: newCaption.value || null };
    const tmpImages = newPostFiles.value.filter((f) => f && f.startsWith("tmp-"));
    if (tmpImages.length > 0) {
      body.tmp_post_images = tmpImages;
    }
    await client(apiUrl.value, { method: "POST", body });
    newCaption.value = "";
    newPostFiles.value = [];
    showAdd.value = false;
    toast.success("Post created");
    refresh();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to create post");
  } finally {
    creating.value = false;
  }
}

async function downloadPostImages(post) {
  const allImages = getAllImages(post);
  if (!allImages.length) return;

  // Determine which images to download
  const selected = selectedImages.value[post.id];
  const imagesToDownload = selected?.size
    ? allImages.filter((img) => selected.has(img.id))
    : allImages;

  if (!imagesToDownload.length) return;

  downloadingPostId.value = post.id;

  try {
    if (imagesToDownload.length === 1) {
      // Single image: download directly
      const img = imagesToDownload[0];
      const blob = await client(`/api/media/${img.id}/download`, { responseType: "blob" });
      const blobUrl = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = blobUrl;
      link.download = img.file_name || `promo-${post.id}-${img.id}.jpg`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(blobUrl);
    } else {
      // Multiple images: zip them
      const zip = new JSZip();

      for (const img of imagesToDownload) {
        const blob = await client(`/api/media/${img.id}/download`, { responseType: "blob" });
        const fileName = img.file_name || `promo-${post.id}-${img.id}.jpg`;
        zip.file(fileName, blob);
      }

      const zipBlob = await zip.generateAsync({ type: "blob" });
      const blobUrl = window.URL.createObjectURL(zipBlob);
      const link = document.createElement("a");
      link.href = blobUrl;
      const brandName = (props.brandEvent?.brand?.name || 'brand').replace(/[^a-zA-Z0-9]/g, '_').toLowerCase();
      link.download = `promo_images_${brandName}.zip`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(blobUrl);
    }

    toast.success("Download complete");
  } catch (e) {
    toast.error("Failed to download");
  } finally {
    downloadingPostId.value = null;
  }
}

// Delete dialog state
const showDeleteDialog = ref(false);
const deleting = ref(false);
const deleteDialogTitle = ref("");
const deleteDialogDescription = ref("");
const pendingDeleteAction = ref(null);

function confirmDeleteAllImages(post) {
  const images = getAllImages(post);
  deleteDialogTitle.value = "Delete all images";
  deleteDialogDescription.value = `Are you sure you want to delete all ${images.length} image${images.length > 1 ? 's' : ''} from this post? This action cannot be undone.`;
  pendingDeleteAction.value = { type: "all", post, imageIds: images.map((img) => img.id) };
  showDeleteDialog.value = true;
}

function confirmDeleteImages(post) {
  const selected = selectedImages.value[post.id];
  if (!selected?.size) return;
  const count = selected.size;
  deleteDialogTitle.value = `Delete ${count} image${count > 1 ? 's' : ''}`;
  deleteDialogDescription.value = `Are you sure you want to delete ${count} selected image${count > 1 ? 's' : ''}? This action cannot be undone.`;
  pendingDeleteAction.value = { type: "selected", post, imageIds: [...selected] };
  showDeleteDialog.value = true;
}

async function executeDelete() {
  if (!pendingDeleteAction.value) return;

  const { type, post, imageIds } = pendingDeleteAction.value;
  deleting.value = true;

  try {
    if (type === "post") {
      await client(`${apiUrl.value}/${post.id}`, { method: "DELETE" });
      toast.success("Post deleted");
    } else {
      await client(`${apiUrl.value}/${post.id}`, {
        method: "PUT",
        body: { delete_media_ids: imageIds },
      });
      const count = imageIds.length;
      toast.success(`${count} image${count > 1 ? 's' : ''} deleted`);
    }

    // Clear selection for this post
    delete selectedImages.value[post.id];
    selectedImages.value = { ...selectedImages.value };

    refresh();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to delete images");
  } finally {
    deleting.value = false;
    showDeleteDialog.value = false;
    pendingDeleteAction.value = null;
  }
}

// Add images to existing post
const addImagesPondRef = ref(null);
const addingImagesPostId = ref(null);
const addImageFiles = ref([]);
const savingAddImages = ref(false);

function startAddImages(post) {
  addingImagesPostId.value = post.id;
  addImageFiles.value = [];
}

function cancelAddImages() {
  addingImagesPostId.value = null;
  addImageFiles.value = [];
}

async function saveAddImages(postId) {
  savingAddImages.value = true;
  try {
    const tmpImages = addImageFiles.value.filter((f) => f && f.startsWith("tmp-"));
    if (tmpImages.length === 0) return;

    await client(`${apiUrl.value}/${postId}`, {
      method: "PUT",
      body: { tmp_post_images: tmpImages },
    });
    cancelAddImages();
    toast.success("Images added");
    refresh();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to add images");
  } finally {
    savingAddImages.value = false;
  }
}

// Delete entire post
function confirmDeletePost(post) {
  deleteDialogTitle.value = "Delete post";
  deleteDialogDescription.value = "Are you sure you want to delete this post? This action cannot be undone.";
  pendingDeleteAction.value = { type: "post", post, imageIds: [] };
  showDeleteDialog.value = true;
}

async function deletePost(postId) {
  try {
    await client(`${apiUrl.value}/${postId}`, { method: "DELETE" });
    toast.success("Post deleted");
    refresh();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to delete post");
  }
}
</script>
