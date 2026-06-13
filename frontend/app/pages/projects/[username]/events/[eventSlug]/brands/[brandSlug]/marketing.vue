<template>
  <div class="flex flex-col gap-y-6">
    <div class="flex items-start justify-between gap-x-2">
      <div class="space-y-1">
        <h3 class="page-title">Promotion Posts</h3>
        <p class="page-description">Marketing materials for this brand.</p>
      </div>
      <div v-if="event?.can_edit" class="flex items-center gap-2">
        <Button variant="outline" size="iconSm" class="shrink-0" @click="showSettings = true">
          <Icon name="hugeicons:settings-01" class="size-4.5" />
        </Button>
        <Button size="sm" class="shrink-0" @click="showAdd = true">
          <Icon name="hugeicons:add-01" class="size-4" />
          Add Post
        </Button>
      </div>
    </div>

    <div v-if="pending" class="flex items-center justify-center py-10">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <div v-else-if="posts?.length" class="grid grid-cols-1 gap-y-10">
      <div v-for="post in posts" :key="post.id">
        <!-- Reorder mode -->
        <div v-if="reorderingPostId === post.id" class="space-y-3 p-4">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium tracking-tight">Reorder Images</p>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Drag to reorder</p>
          </div>
          <div
            :ref="
              (el) => {
                if (el) reorderGridRefs[post.id] = el;
              }
            "
            class="grid grid-cols-2 gap-2 sm:grid-cols-3"
          >
            <div
              v-for="img in reorderImages"
              :key="img.id"
              :data-id="img.id"
              class="group relative aspect-square cursor-grab overflow-hidden rounded-lg border active:cursor-grabbing"
            >
              <img :src="img.md || img.original" alt="" class="size-full object-cover" />
              <div
                class="bg-foreground/60 text-background absolute top-1 left-1 flex size-5 items-center justify-center rounded text-[10px] font-medium tracking-tight"
              >
                {{ reorderImages.indexOf(img) + 1 }}
              </div>
            </div>
          </div>
          <div class="flex gap-2">
            <Button size="sm" :disabled="savingReorder" @click="saveReorder(post.id)">
              <Icon v-if="savingReorder" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
              Save
            </Button>
            <Button size="sm" variant="ghost" @click="cancelReorder">Cancel</Button>
          </div>
        </div>

        <!-- Normal view -->
        <div class="frame mx-auto max-w-md" v-else>
          <!-- Post head (mirrors public brand page) -->
          <div v-if="getAllImages(post).length" class="m-4 flex items-center gap-3">
            <component
              :is="brandInstagramLink ? 'nuxt-link' : 'div'"
              :to="brandInstagramLink ? brandInstagramLink.url : undefined"
              :target="brandInstagramLink ? '_blank' : undefined"
              class="shrink-0"
            >
              <Avatar
                :model="{ name: brandName, profile_image: brandLogo }"
                size="sm"
                class="size-9"
                rounded="rounded-full"
                :colorful="false"
                :gradient-frame="!!brandInstagramLink"
              />
            </component>

            <component
              :is="brandInstagramLink ? 'nuxt-link' : 'span'"
              :to="brandInstagramLink ? brandInstagramLink.url : undefined"
              :target="brandInstagramLink ? '_blank' : undefined"
              class="text-foreground font-semibold tracking-tight"
            >
              {{ brandInstagramUsername ?? brandName }}
            </component>

            <span
              v-if="post.created_at"
              class="text-muted-foreground ml-auto text-sm tracking-tight"
            >
              {{ $dayjs(post.created_at).fromNow() }}
            </span>
          </div>

          <!-- Image grid (mirrors public: Lightbox + firstSpansLarge). All images stay
               visible so per-image selection / download / delete keeps working. -->
          <Lightbox
            v-if="getAllImages(post).length"
            :items="getAllImages(post)"
            thumbnailKey="md"
            fullKey="xl"
            :first-spans-large="getAllImages(post).length > 2"
            :gridClass="promoGridClass(getAllImages(post).length)"
            rounded="rounded-2xl"
            class="gap-1.5"
            show-caption
            show-counter
            show-thumbnails
          >
            <template #trigger="{ openAt }">
              <div :class="promoGridClass(getAllImages(post).length)">
                <div
                  v-for="(img, idx) in getAllImages(post)"
                  :key="img.id"
                  class="group/tile bg-muted relative overflow-hidden rounded-2xl"
                  :class="promoThumbClass(idx, getAllImages(post).length)"
                >
                  <button type="button" class="block size-full cursor-zoom-in" @click="openAt(idx)">
                    <img
                      :src="img.md || img.url"
                      :alt="img.alt || ''"
                      class="size-full object-cover transition-opacity group-hover/tile:opacity-90"
                      loading="lazy"
                    />
                  </button>

                  <div
                    v-if="event?.can_edit"
                    class="absolute top-2 left-2 z-10 transition-opacity"
                    :class="
                      selectedImages[post.id]?.size
                        ? 'opacity-100'
                        : 'opacity-0 group-hover/tile:opacity-100'
                    "
                  >
                    <Checkbox
                      :model-value="selectedImages[post.id]?.has(img.id) || false"
                      class="data-[state=checked]:!bg-primary data-[state=checked]:!border-primary !size-5 !rounded-md !border-white/80 !bg-black/30 !shadow-md"
                      @update:model-value="toggleImageSelection(post.id, img.id)"
                    />
                  </div>
                </div>
              </div>
            </template>
          </Lightbox>

          <div class="frame-panel">
            <!-- Inline add images form -->
            <div v-if="addingImagesPostId === post.id" class="space-y-3">
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
                  <Icon
                    v-if="savingAddImages"
                    name="svg-spinners:ring-resize"
                    class="mr-1.5 size-4"
                  />
                  Upload
                </Button>
                <Button size="sm" variant="ghost" @click="cancelAddImages">Cancel</Button>
              </div>
            </div>

            <!-- Caption & Actions -->
            <div class="space-y-3" :class="{ '': !getAllImages(post).length }">
              <!-- Edit caption mode -->
              <template v-if="editingPostId === post.id">
                <div class="space-y-3">
                  <Textarea v-model="editCaption" rows="2" placeholder="Write a caption..." />
                  <div class="flex gap-2">
                    <Button size="sm" :disabled="updatingPost" @click="updatePost(post.id)">
                      <Icon
                        v-if="updatingPost"
                        name="svg-spinners:ring-resize"
                        class="mr-1.5 size-4"
                      />
                      Save
                      <KbdGroup>
                        <Kbd>{{ metaSymbol }}</Kbd>
                        <Kbd>S</Kbd>
                      </KbdGroup>
                    </Button>
                    <Button size="sm" variant="ghost" @click="editingPostId = null">Cancel</Button>
                  </div>
                </div>
              </template>

              <!-- View mode -->
              <template v-else>
                <p
                  v-if="post.caption"
                  class="text-sm tracking-tight whitespace-pre-wrap sm:text-base"
                >
                  {{ post.caption }}
                </p>
                <p v-else class="text-muted-foreground text-sm tracking-tight italic">No caption</p>

                <div class="flex flex-wrap items-center gap-2">
                  <template v-if="event?.can_edit">
                    <Button
                      variant="outline"
                      size="sm"
                      @click="
                        editingPostId = post.id;
                        editCaption = post.caption || '';
                      "
                    >
                      <Icon name="hugeicons:edit-02" class="size-4" />
                      Edit Caption
                    </Button>
                  </template>

                  <Button
                    v-if="post.caption"
                    variant="outline"
                    size="sm"
                    @click="copy(post.caption)"
                  >
                    <Icon :name="copied ? 'lucide:check' : 'hugeicons:copy-01'" class="size-4" />
                    <span class="inline-grid">
                      <span class="col-start-1 row-start-1" :class="copied ? 'invisible' : ''"
                        >Copy Caption</span
                      >
                      <span class="col-start-1 row-start-1" :class="copied ? '' : 'invisible'"
                        >Copied</span
                      >
                    </span>
                  </Button>

                  <template v-if="event?.can_edit">
                    <!-- Add images -->
                    <Button
                      v-if="getAllImages(post).length < 20"
                      variant="outline"
                      size="sm"
                      @click="startAddImages(post)"
                    >
                      <Icon name="hugeicons:image-add-01" class="size-4" />
                      Add Images
                    </Button>

                    <!-- Reorder images -->
                    <Button
                      v-if="getAllImages(post).length > 1"
                      variant="outline"
                      size="sm"
                      @click="startReorder(post)"
                    >
                      <Icon name="hugeicons:drag-drop-vertical" class="size-4" />
                      Reorder
                    </Button>

                    <!-- Download -->
                    <Button
                      v-if="getAllImages(post).length"
                      variant="outline"
                      size="sm"
                      :disabled="downloadingPostId === post.id"
                      @click="downloadPostImages(post)"
                    >
                      <Icon
                        :name="
                          downloadingPostId === post.id
                            ? 'svg-spinners:ring-resize'
                            : 'hugeicons:download-01'
                        "
                        class="size-4"
                      />
                      {{
                        selectedImages[post.id]?.size
                          ? `Download (${selectedImages[post.id].size})`
                          : "Download All"
                      }}
                    </Button>

                    <!-- Delete selected images -->
                    <Button
                      v-if="selectedImages[post.id]?.size"
                      variant="outline-destructive"
                      size="sm"
                      @click="confirmDeleteImages(post)"
                    >
                      <Icon name="hugeicons:delete-02" class="size-4" />
                      Delete {{ selectedImages[post.id].size }} Image{{
                        selectedImages[post.id].size > 1 ? "s" : ""
                      }}
                    </Button>

                    <!-- Delete all images -->
                    <Button
                      v-if="getAllImages(post).length && !selectedImages[post.id]?.size"
                      variant="outline-destructive"
                      size="sm"
                      @click="confirmDeleteAllImages(post)"
                    >
                      <Icon name="hugeicons:delete-02" class="size-4" />
                      Delete Images
                    </Button>

                    <!-- Delete post (when no images left) -->
                    <Button
                      v-if="!getAllImages(post).length"
                      variant="outline-destructive"
                      size="sm"
                      @click="confirmDeletePost(post)"
                    >
                      <Icon name="hugeicons:delete-02" class="size-4" />
                      Delete Post
                    </Button>
                  </template>
                </div>
              </template>
            </div>
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
              <Button type="submit" size="sm" :disabled="creating || !newPostFiles.length">
                <Icon v-if="creating" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                Upload Post
              </Button>
            </div>
          </form>
        </div>
      </template>
    </DialogResponsive>

    <!-- Settings dialog -->
    <DialogResponsive v-model:open="showSettings" dialog-max-width="400px">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-6">
          <div class="text-foreground text-lg font-medium tracking-tight">Settings</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            Configure promotion post settings for this brand.
          </p>
          <form class="mt-4 space-y-4" @submit.prevent="saveLimit">
            <div class="space-y-2">
              <Label for="promotion_post_limit">Post Limit</Label>
              <InputNumber
                id="promotion_post_limit"
                v-model="postLimit"
                :min="1"
                :max="100"
                placeholder="1"
              />
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Maximum number of posts the exhibitor can upload.
              </p>
            </div>
            <div class="flex justify-end gap-2">
              <Button variant="outline" size="sm" type="button" @click="showSettings = false">
                Cancel
              </Button>
              <Button type="submit" size="sm" :disabled="savingLimit">
                <Icon v-if="savingLimit" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                Save
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
            <Button variant="outline" size="sm" @click="showDeleteDialog = false">Cancel</Button>
            <Button variant="destructive" size="sm" :disabled="deleting" @click="executeDelete">
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
import { useClipboard } from "@vueuse/core";
import JSZip from "jszip";
import { toast } from "vue-sonner";

const { copy, copied } = useClipboard();

const props = defineProps({ brandEvent: Object });
const emit = defineEmits(["refresh"]);
const { metaSymbol } = useShortcuts();
const route = useRoute();
const client = useSanctumClient();
const event = inject("event");
const { $dayjs } = useNuxtApp();

// --- Promotion display (mirrors the public brand page) ---
const brandName = computed(() => props.brandEvent?.brand?.name ?? "Brand");
const brandLogo = computed(() => props.brandEvent?.brand?.brand_logo ?? null);
const brandInstagramLink = computed(() =>
  (props.brandEvent?.brand?.links ?? []).find((l) => l.label?.toLowerCase() === "instagram")
);
const brandInstagramUsername = computed(() => {
  if (!brandInstagramLink.value) return null;
  try {
    return new URL(brandInstagramLink.value.url).pathname.replace(/\//g, "") || null;
  } catch {
    return null;
  }
});

const promoGridClass = (count) => (count <= 1 ? "grid grid-cols-1" : "grid grid-cols-2 gap-1.5");

const promoThumbClass = (index, total) =>
  total > 2 && index === 0 ? "col-span-2 row-span-2 aspect-auto" : "aspect-square";

// Settings
const showSettings = ref(false);
const postLimit = ref(props.brandEvent?.promotion_post_limit || 1);
const savingLimit = ref(false);

// Add post
const showAdd = ref(false);
const newPostPondRef = ref(null);
const newPostFiles = ref([]);
const newCaption = ref("");
const creating = ref(false);

// Data
const brandUrl = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${route.params.brandSlug}`
);
const apiUrl = computed(() => `${brandUrl.value}/promotion-posts`);
const data = ref(null);
const pending = ref(true);
const posts = computed(() => data.value?.data || []);

// Selection & download
const selectedImages = ref({});
const downloadingPostId = ref(null);

// Edit caption
const editingPostId = ref(null);
const editCaption = ref("");
const updatingPost = ref(false);

// Reorder
const reorderingPostId = ref(null);
const reorderImages = ref([]);
const reorderGridRefs = reactive({});
const savingReorder = ref(false);

const reorderEl = computed(() =>
  reorderingPostId.value ? reorderGridRefs[reorderingPostId.value] : null
);
useSortableList(reorderEl, reorderImages, {
  sortableOptions: {
    handle: undefined,
    ghostClass: "opacity-30",
  },
});

// Delete dialog
const showDeleteDialog = ref(false);
const deleting = ref(false);
const deleteDialogTitle = ref("");
const deleteDialogDescription = ref("");
const pendingDeleteAction = ref(null);

// Add images to existing post
const addImagesPondRef = ref(null);
const addingImagesPostId = ref(null);
const addImageFiles = ref([]);
const savingAddImages = ref(false);

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
  selectedImages.value = { ...selectedImages.value };
}

async function refresh() {
  pending.value = true;
  try {
    data.value = await client(apiUrl.value);
  } catch {}
  pending.value = false;
}

onMounted(() => refresh());

async function saveLimit() {
  savingLimit.value = true;
  try {
    await client(brandUrl.value, {
      method: "PUT",
      body: { promotion_post_limit: postLimit.value },
    });
    toast.success("Post limit updated");
    showSettings.value = false;
    emit("refresh");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to update");
  } finally {
    savingLimit.value = false;
  }
}

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

async function updatePost(postId) {
  updatingPost.value = true;
  try {
    await client(`${apiUrl.value}/${postId}`, {
      method: "PUT",
      body: { caption: editCaption.value },
    });
    // Update in-place
    const post = posts.value.find((p) => p.id === postId);
    if (post) post.caption = editCaption.value;
    editingPostId.value = null;
    toast.success("Caption updated");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to update");
  } finally {
    updatingPost.value = false;
  }
}

// Reorder
function startReorder(post) {
  reorderingPostId.value = post.id;
  reorderImages.value = [...getAllImages(post)];
}

function cancelReorder() {
  reorderingPostId.value = null;
  reorderImages.value = [];
}

async function saveReorder(postId) {
  savingReorder.value = true;
  try {
    const mediaIds = reorderImages.value.map((img) => img.id);
    await client(`${apiUrl.value}/${postId}/reorder-media`, {
      method: "POST",
      body: { media_ids: mediaIds },
    });
    cancelReorder();
    toast.success("Order saved");
    refresh();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to reorder");
  } finally {
    savingReorder.value = false;
  }
}

// Download
async function downloadPostImages(post) {
  const allImages = getAllImages(post);
  if (!allImages.length) return;

  const selected = selectedImages.value[post.id];
  const imagesToDownload = selected?.size
    ? allImages.filter((img) => selected.has(img.id))
    : allImages;

  if (!imagesToDownload.length) return;
  downloadingPostId.value = post.id;

  try {
    if (imagesToDownload.length === 1) {
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
      const zip = new JSZip();
      for (const img of imagesToDownload) {
        const blob = await client(`/api/media/${img.id}/download`, { responseType: "blob" });
        zip.file(img.file_name || `promo-${post.id}-${img.id}.jpg`, blob);
      }
      const zipBlob = await zip.generateAsync({ type: "blob" });
      const blobUrl = window.URL.createObjectURL(zipBlob);
      const link = document.createElement("a");
      link.href = blobUrl;
      const brandName = (props.brandEvent?.brand?.name || "brand")
        .replace(/[^a-zA-Z0-9]/g, "_")
        .toLowerCase();
      link.download = `promo_images_${brandName}.zip`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(blobUrl);
    }
    toast.success("Download complete");
  } catch {
    toast.error("Failed to download");
  } finally {
    downloadingPostId.value = null;
  }
}

// Add images
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

// Delete
function confirmDeleteAllImages(post) {
  const images = getAllImages(post);
  deleteDialogTitle.value = "Delete all images";
  deleteDialogDescription.value = `Are you sure you want to delete all ${images.length} image${images.length > 1 ? "s" : ""} from this post? This action cannot be undone.`;
  pendingDeleteAction.value = { type: "all", post, imageIds: images.map((img) => img.id) };
  showDeleteDialog.value = true;
}

function confirmDeleteImages(post) {
  const selected = selectedImages.value[post.id];
  if (!selected?.size) return;
  const count = selected.size;
  deleteDialogTitle.value = `Delete ${count} image${count > 1 ? "s" : ""}`;
  deleteDialogDescription.value = `Are you sure you want to delete ${count} selected image${count > 1 ? "s" : ""}? This action cannot be undone.`;
  pendingDeleteAction.value = { type: "selected", post, imageIds: [...selected] };
  showDeleteDialog.value = true;
}

function confirmDeletePost(post) {
  deleteDialogTitle.value = "Delete post";
  deleteDialogDescription.value =
    "Are you sure you want to delete this post? This action cannot be undone.";
  pendingDeleteAction.value = { type: "post", post, imageIds: [] };
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
      toast.success(`${imageIds.length} image${imageIds.length > 1 ? "s" : ""} deleted`);
    }

    delete selectedImages.value[post.id];
    selectedImages.value = { ...selectedImages.value };
    refresh();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to delete");
  } finally {
    deleting.value = false;
    showDeleteDialog.value = false;
    pendingDeleteAction.value = null;
  }
}

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      if (editingPostId.value) {
        updatePost(editingPostId.value);
      }
    },
  },
});
</script>
