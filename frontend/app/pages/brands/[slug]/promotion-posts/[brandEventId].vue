<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6">
    <!-- Back + Title -->
    <div class="flex flex-col items-start gap-y-3">
      <BackButton :destination="`/brands/${brandSlug}`" :show-label="true" />
      <div class="flex flex-col gap-y-1">
        <h2 class="page-title">{{ $t("promotionPosts.title") }}</h2>
        <p v-if="pageData" class="page-description">
          {{ pageData.brand?.name }} &middot; {{ pageData.event?.title }}
        </p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="pending" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <template v-else-if="pageData">
      <!-- Event Info Card -->
      <div class="border-border flex items-center gap-x-3 rounded-xl border p-4">
        <img
          v-if="pageData.event?.poster_image?.sm"
          :src="pageData.event.poster_image.sm"
          :alt="pageData.event.title"
          class="size-12 shrink-0 rounded-lg object-cover"
        />
        <div
          v-else
          class="bg-muted text-muted-foreground flex size-12 shrink-0 items-center justify-center rounded-lg"
        >
          <Icon name="hugeicons:calendar-03" class="size-5" />
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium tracking-tight">{{ pageData.event?.title }}</p>
          <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
            <span v-if="pageData.event?.date_label">{{ pageData.event.date_label }}</span>
            <template v-if="pageData.event?.date_label && pageData.event?.location">
              &middot;
            </template>
            <span v-if="pageData.event?.location">{{ pageData.event.location }}</span>
          </p>
        </div>
      </div>

      <!-- Deadline Banner -->
      <div
        v-if="pageData.promotion_post_deadline"
        :class="[
          'flex items-center gap-x-3 rounded-lg border px-4 py-3 text-xs tracking-tight sm:text-sm',
          isDeadlinePassed
            ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300'
            : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300',
        ]"
      >
        <Icon
          :name="isDeadlinePassed ? 'hugeicons:alert-02' : 'hugeicons:clock-01'"
          class="size-4 shrink-0"
        />
        <span v-if="isDeadlinePassed">{{ $t("promotionPosts.deadlinePassed") }}</span>
        <span v-else>
          {{
            $t("promotionPosts.deadlineInfo", {
              date: formatDeadline(pageData.promotion_post_deadline),
            })
          }}
        </span>
      </div>

      <!-- Limit Reached Banner -->
      <div
        v-if="isLimitReached && !isDeadlinePassed"
        class="border-border text-foreground bg-muted flex items-center gap-x-3 rounded-lg border px-4 py-3 text-xs tracking-tight sm:text-sm"
      >
        <Icon name="hugeicons:information-circle" class="size-4 shrink-0" />
        <span>{{
          $t("promotionPosts.limitReached", { limit: pageData.promotion_post_limit })
        }}</span>
      </div>

      <!-- Upload New Post -->
      <div v-if="!isLimitReached && !isDeadlinePassed" class="frame">
        <div class="frame-header">
          <div class="flex items-center justify-between">
            <div class="frame-title">{{ $t("promotionPosts.uploadNewPost") }}</div>
            <span
              v-if="pageData?.promotion_post_limit"
              class="text-muted-foreground text-xs tracking-tight sm:text-sm"
            >
              {{ posts.length }} / {{ pageData.promotion_post_limit }}
            </span>
          </div>
        </div>
        <div class="frame-panel">
          <form class="space-y-4" @submit.prevent="createPost">
            <div class="space-y-2">
              <InputFile
                ref="newPostPondRef"
                v-model="newPostFiles"
                :accepted-file-types="['image/jpeg', 'image/png', 'image/jpg', 'image/webp']"
                :allow-multiple="true"
                :max-files="20"
              />
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                {{ $t("promotionPosts.aspectRatio") }} &middot;
                {{ $t("promotionPosts.maxImages") }}
              </p>
            </div>
            <div class="space-y-2">
              <Label for="new_caption">{{ $t("promotionPosts.caption") }}</Label>
              <Textarea
                id="new_caption"
                v-model="newCaption"
                rows="2"
                :placeholder="$t('promotionPosts.writeCaption')"
              />
            </div>
            <Button
              type="submit"
              size="sm"
              :disabled="creating || !newPostFiles.length || isDeadlinePassed || isLimitReached"
            >
              <Icon v-if="creating" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
              {{ $t("promotionPosts.uploadPost") }}
            </Button>
          </form>
        </div>
      </div>

      <!-- Existing Posts -->
      <div class="space-y-3">
        <h3 class="text-sm font-medium tracking-tight">
          {{ $t("promotionPosts.posts", { count: posts.length }) }}
        </h3>

        <div v-if="posts.length" class="space-y-3">
          <div
            v-for="post in posts"
            :key="post.id"
            class="border-border overflow-hidden rounded-xl border"
          >
            <!-- Reorder mode -->
            <div v-if="reorderingPostId === post.id" class="space-y-3 p-4">
              <div class="flex items-center justify-between">
                <p class="text-sm font-medium tracking-tight">
                  {{ $t("promotionPosts.reorderImages") }}
                </p>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  {{ $t("promotionPosts.dragToReorder") }}
                </p>
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
                  <img :src="img.lg || img.original" alt="" class="size-full object-cover" />
                  <div
                    class="bg-foreground/60 text-background absolute top-1 left-1 flex size-5 items-center justify-center rounded text-[10px] font-medium tracking-tight"
                  >
                    {{ reorderImages.indexOf(img) + 1 }}
                  </div>
                </div>
              </div>
              <div class="flex gap-2">
                <Button size="sm" :disabled="savingReorder" @click="saveReorder(post.id)">
                  <Icon
                    v-if="savingReorder"
                    name="svg-spinners:ring-resize"
                    class="mr-1.5 size-4"
                  />
                  {{ $t("common.save") }}
                </Button>
                <Button size="sm" variant="ghost" @click="cancelReorder">
                  {{ $t("common.cancel") }}
                </Button>
              </div>
            </div>

            <!-- Normal view -->
            <template v-else>
              <!-- Image grid -->
              <div
                v-if="getPostImages(post).length"
                class="grid gap-1 p-4"
                :class="
                  getPostImages(post).length === 1 ? 'grid-cols-1' : 'grid-cols-2 sm:grid-cols-3'
                "
              >
                <div
                  v-for="img in getPostImages(post)"
                  :key="img.id || img.original"
                  class="relative aspect-square overflow-hidden rounded-lg"
                >
                  <img
                    :src="img.lg || img.original"
                    alt=""
                    class="size-full object-cover"
                    @error="
                      (e) => {
                        if (img.original && e.target.src !== img.original)
                          e.target.src = img.original;
                      }
                    "
                  />
                </div>
              </div>

              <!-- Add images form (inline, below existing images) -->
              <div v-if="addingImagesPostId === post.id" class="space-y-3 px-4 pb-3">
                <p class="text-sm font-medium tracking-tight">Add Images</p>
                <InputFile
                  ref="addImagesPondRef"
                  v-model="addImageFiles"
                  :accepted-file-types="['image/jpeg', 'image/png', 'image/jpg', 'image/webp']"
                  :allow-multiple="true"
                  :max-files="20"
                />
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  {{ $t("promotionPosts.aspectRatio") }}
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
                  <Button size="sm" variant="ghost" @click="cancelAddImages">
                    {{ $t("common.cancel") }}
                  </Button>
                </div>
              </div>

              <div class="space-y-3 px-4 pb-4" :class="{ 'pt-4': !getPostImages(post).length }">
                <!-- Edit mode -->
                <template v-if="editingPostId === post.id">
                  <div class="space-y-3">
                    <Textarea v-model="editCaption" rows="2" />
                    <div class="flex gap-2">
                      <Button size="sm" :disabled="updatingPost" @click="updatePost(post.id)">
                        <Icon
                          v-if="updatingPost"
                          name="svg-spinners:ring-resize"
                          class="mr-1.5 size-4"
                        />
                        {{ $t("common.save") }}
                      </Button>
                      <Button size="sm" variant="ghost" @click="editingPostId = null">
                        {{ $t("common.cancel") }}
                      </Button>
                    </div>
                  </div>
                </template>
                <!-- View mode -->
                <template v-else>
                  <p v-if="post.caption" class="text-sm tracking-tight">{{ post.caption }}</p>
                  <p v-else class="text-muted-foreground text-sm tracking-tight italic">
                    {{ $t("promotionPosts.noCaption") }}
                  </p>
                  <div class="flex flex-wrap gap-2">
                    <Button
                      size="sm"
                      variant="ghost"
                      @click="
                        editingPostId = post.id;
                        editCaption = post.caption || '';
                      "
                    >
                      <Icon name="hugeicons:edit-02" class="mr-1 size-3.5" />
                      {{ $t("common.edit") }}
                    </Button>
                    <Button
                      v-if="getPostImages(post).length < 20"
                      size="sm"
                      variant="ghost"
                      @click="startAddImages(post)"
                    >
                      <Icon name="hugeicons:image-add-01" class="mr-1 size-3.5" />
                      Add Images
                    </Button>
                    <Button
                      v-if="getPostImages(post).length > 1"
                      size="sm"
                      variant="ghost"
                      @click="startReorder(post)"
                    >
                      <Icon name="hugeicons:drag-drop" class="mr-1 size-3.5" />
                      {{ $t("promotionPosts.reorder") }}
                    </Button>
                    <Button
                      size="sm"
                      variant="ghost"
                      class="text-destructive hover:text-destructive"
                      :disabled="deletingPostId === post.id"
                      @click="confirmingDeleteId = post.id"
                    >
                      <Icon
                        :name="
                          deletingPostId === post.id
                            ? 'svg-spinners:ring-resize'
                            : 'hugeicons:delete-02'
                        "
                        class="mr-1 size-3.5"
                      />
                      {{ $t("common.delete") }}
                    </Button>
                  </div>
                </template>
              </div>
            </template>
          </div>
        </div>

        <div
          v-else
          class="border-border flex flex-col items-center gap-3 rounded-xl border px-4 py-12"
        >
          <div class="bg-muted flex size-12 items-center justify-center rounded-full">
            <Icon name="hugeicons:image-02" class="text-muted-foreground size-6" />
          </div>
          <p class="text-muted-foreground text-sm tracking-tight">
            {{ $t("promotionPosts.noPostsYet") }}
          </p>
        </div>
      </div>
    </template>

    <!-- Delete Confirmation Dialog -->
    <DialogResponsive v-model:open="showDeleteDialog" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-6">
          <div class="text-foreground text-lg font-medium tracking-tight">
            {{ $t("promotionPosts.deletePost") }}
          </div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            {{ $t("promotionPosts.deleteConfirm") }}
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" size="sm" @click="confirmingDeleteId = null">
              {{ $t("common.cancel") }}
            </Button>
            <Button
              variant="destructive"
              size="sm"
              :disabled="deletingPostId !== null"
              @click="deletePost(confirmingDeleteId)"
            >
              <Icon v-if="deletingPostId" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
              {{ $t("common.delete") }}
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { useSortable } from "@vueuse/integrations/useSortable";
import { toast } from "vue-sonner";

const { t } = useI18n();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const client = useSanctumClient();
const brandSlug = route.params.slug;
const brandEventId = route.params.brandEventId;
const apiBase = `/api/exhibitor/brands/${brandSlug}/events/${brandEventId}/promotion-posts`;

const pending = ref(true);
const pageData = ref(null);
const posts = ref([]);

const isDeadlinePassed = computed(() => {
  if (!pageData.value?.promotion_post_deadline) return false;
  return new Date(pageData.value.promotion_post_deadline) < new Date();
});

const isLimitReached = computed(() => {
  if (!pageData.value?.promotion_post_limit) return false;
  return posts.value.length >= pageData.value.promotion_post_limit;
});

function formatDeadline(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  return d.toLocaleDateString(undefined, {
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

// New post form (multi-image)
const newPostPondRef = ref(null);
const newPostFiles = ref([]);
const newCaption = ref("");
const creating = ref(false);

// Reorder state
const reorderingPostId = ref(null);
const reorderImages = ref([]);
const reorderGridRefs = reactive({});
const savingReorder = ref(false);
let sortableInstance = null;

function startReorder(post) {
  reorderingPostId.value = post.id;
  reorderImages.value = [...post.post_images];
  nextTick(() => {
    const el = reorderGridRefs[post.id];
    if (el) {
      sortableInstance = useSortable(el, reorderImages, {
        animation: 200,
        ghostClass: "opacity-30",
      });
    }
  });
}

function cancelReorder() {
  reorderingPostId.value = null;
  reorderImages.value = [];
  sortableInstance = null;
}

async function saveReorder(postId) {
  savingReorder.value = true;
  try {
    const mediaIds = reorderImages.value.map((img) => img.id);
    const res = await client(`${apiBase}/${postId}/reorder-media`, {
      method: "POST",
      body: { media_ids: mediaIds },
    });
    const idx = posts.value.findIndex((p) => p.id === postId);
    if (idx !== -1) {
      posts.value[idx] = res.data;
    }
    reorderingPostId.value = null;
    reorderImages.value = [];
    sortableInstance = null;
    toast.success(t("promotionPosts.reorderSaved"));
  } catch (e) {
    toast.error(e?.data?.message || t("promotionPosts.failedToReorder"));
  } finally {
    savingReorder.value = false;
  }
}

// Helper to get images from a post (supports both post_images and legacy post_image)
function getPostImages(post) {
  if (post.post_images?.length) return post.post_images;
  if (post.post_image) return [post.post_image];
  return [];
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

    const res = await client(`${apiBase}/${postId}`, {
      method: "PUT",
      body: { tmp_post_images: tmpImages },
    });
    const idx = posts.value.findIndex((p) => p.id === postId);
    if (idx !== -1) {
      posts.value[idx] = res.data;
    }
    cancelAddImages();
    toast.success(t("promotionPosts.postUpdated"));
  } catch (e) {
    toast.error(e?.data?.message || t("promotionPosts.failedToUpdate"));
  } finally {
    savingAddImages.value = false;
  }
}

// Edit state
const editingPostId = ref(null);
const editCaption = ref("");
const updatingPost = ref(false);

// Delete state
const confirmingDeleteId = ref(null);
const deletingPostId = ref(null);
const showDeleteDialog = computed({
  get: () => confirmingDeleteId.value !== null,
  set: (v) => {
    if (!v) confirmingDeleteId.value = null;
  },
});

const fetchData = async () => {
  try {
    const res = await client(apiBase);
    pageData.value = res.data;
    posts.value = res.data.posts || [];
  } catch (e) {
    console.error("Failed to fetch promotion posts:", e);
  }
  pending.value = false;
};

const createPost = async () => {
  creating.value = true;
  try {
    const body = { caption: newCaption.value };

    const tmpImages = newPostFiles.value.filter((f) => f && f.startsWith("tmp-"));
    if (tmpImages.length > 0) {
      body.tmp_post_images = tmpImages;
    }

    const res = await client(apiBase, { method: "POST", body });
    posts.value.unshift(res.data);
    newCaption.value = "";
    newPostFiles.value = [];
    toast.success(t("promotionPosts.postUploaded"));
  } catch (e) {
    toast.error(e?.data?.message || t("promotionPosts.failedToUpload"));
  } finally {
    creating.value = false;
  }
};

const updatePost = async (postId) => {
  updatingPost.value = true;
  try {
    const res = await client(`${apiBase}/${postId}`, {
      method: "PUT",
      body: { caption: editCaption.value },
    });
    const idx = posts.value.findIndex((p) => p.id === postId);
    if (idx !== -1) {
      posts.value[idx] = res.data;
    }
    editingPostId.value = null;
    toast.success(t("promotionPosts.postUpdated"));
  } catch (e) {
    toast.error(e?.data?.message || t("promotionPosts.failedToUpdate"));
  } finally {
    updatingPost.value = false;
  }
};

const deletePost = async (postId) => {
  deletingPostId.value = postId;
  try {
    await client(`${apiBase}/${postId}`, { method: "DELETE" });
    posts.value = posts.value.filter((p) => p.id !== postId);
    toast.success(t("promotionPosts.postDeleted"));
  } catch (e) {
    toast.error(e?.data?.message || t("promotionPosts.failedToDelete"));
  } finally {
    deletingPostId.value = null;
    confirmingDeleteId.value = null;
  }
};

onMounted(fetchData);

usePageMeta(null, {
  title: computed(() =>
    pageData.value
      ? `${t("promotionPosts.title")} · ${pageData.value.event?.title}`
      : t("promotionPosts.title")
  ),
});
</script>
