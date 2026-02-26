<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6">
    <!-- Back + Title -->
    <div class="flex items-center gap-x-3">
      <NuxtLink
        :to="`/brands/${brandSlug}`"
        class="text-muted-foreground hover:text-foreground flex size-8 items-center justify-center rounded-lg transition"
      >
        <Icon name="hugeicons:arrow-left-01" class="size-5" />
      </NuxtLink>
      <div class="min-w-0 flex-1">
        <h2 class="truncate text-lg font-bold tracking-tight">{{ $t('promotionPosts.title') }}</h2>
        <p v-if="pageData" class="text-muted-foreground truncate text-xs">
          {{ pageData.brand?.name }} &middot; {{ pageData.event?.title }}
        </p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="pending" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <template v-else-if="pageData">
      <!-- Event Info -->
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
          <p class="truncate text-sm font-medium">{{ pageData.event?.title }}</p>
          <div class="text-muted-foreground flex items-center gap-x-3 text-xs">
            <span v-if="pageData.event?.date_label">{{ pageData.event.date_label }}</span>
            <span v-if="pageData.event?.location">{{ pageData.event.location }}</span>
          </div>
        </div>
      </div>

      <!-- Deadline Banner -->
      <div
        v-if="pageData.promotion_post_deadline"
        :class="[
          'flex items-center gap-x-3 rounded-lg border px-4 py-3 text-sm',
          isDeadlinePassed
            ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300'
            : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300',
        ]"
      >
        <Icon
          :name="isDeadlinePassed ? 'hugeicons:alert-02' : 'hugeicons:clock-01'"
          class="size-4 shrink-0"
        />
        <span v-if="isDeadlinePassed">{{ $t('promotionPosts.deadlinePassed') }}</span>
        <span v-else>
          {{ $t('promotionPosts.deadlineInfo', { date: formatDeadline(pageData.promotion_post_deadline) }) }}
        </span>
      </div>

      <!-- Limit Reached Banner -->
      <div
        v-if="isLimitReached && !isDeadlinePassed"
        class="flex items-center gap-x-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-300"
      >
        <Icon name="hugeicons:information-circle" class="size-4 shrink-0" />
        <span>{{ $t('promotionPosts.limitReached', { limit: pageData.promotion_post_limit }) }}</span>
      </div>

      <!-- Upload New Post -->
      <div class="border-border rounded-xl border">
        <div class="flex items-center gap-x-3 border-b px-5 py-4">
          <Icon name="hugeicons:image-add-01" class="text-muted-foreground size-4" />
          <h3 class="text-sm font-semibold tracking-tight">{{ $t('promotionPosts.uploadNewPost') }}</h3>
          <span v-if="pageData?.promotion_post_limit" class="text-muted-foreground ml-auto text-xs">
            {{ posts.length }} / {{ pageData.promotion_post_limit }}
          </span>
        </div>
        <form class="space-y-4 p-5" @submit.prevent="createPost">
          <div class="space-y-2">
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
              <InputFileImage
                v-for="(_, index) in newPostSlots"
                :key="index"
                v-model="newPostFiles[index]"
                container-class="relative isolate aspect-square w-full"
              />
            </div>
            <div class="flex items-center justify-between">
              <p class="text-muted-foreground text-xs">
                {{ $t('promotionPosts.aspectRatio') }} · {{ $t('promotionPosts.maxImages') }}
              </p>
              <button
                v-if="newPostSlots.length < 20 && hasLastSlotFilled"
                type="button"
                @click="newPostSlots.push(null)"
                class="text-muted-foreground hover:text-foreground flex items-center gap-x-1 text-xs"
              >
                <Icon name="hugeicons:add-01" class="size-3.5" />
                {{ $t('common.add') }}
              </button>
            </div>
          </div>
          <div class="space-y-2">
            <Label for="new_caption">{{ $t('promotionPosts.caption') }}</Label>
            <Textarea id="new_caption" v-model="newCaption" rows="2" :placeholder="$t('promotionPosts.writeCaption')" />
          </div>
          <Button type="submit" size="sm" :disabled="creating || !hasAnyNewFile || isDeadlinePassed || isLimitReached">
            <Icon v-if="creating" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
            {{ $t('promotionPosts.uploadPost') }}
          </Button>
        </form>
      </div>

      <!-- Existing Posts -->
      <div class="space-y-3">
        <h3 class="text-sm font-semibold tracking-tight">
          {{ $t('promotionPosts.posts', { count: posts.length }) }}
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
                <p class="text-sm font-medium">{{ $t('promotionPosts.reorderImages') }}</p>
                <p class="text-muted-foreground text-xs">{{ $t('promotionPosts.dragToReorder') }}</p>
              </div>
              <div :ref="(el) => { if (el) reorderGridRefs[post.id] = el; }" class="grid grid-cols-4 gap-2 sm:grid-cols-5">
                <div
                  v-for="img in reorderImages"
                  :key="img.id"
                  :data-id="img.id"
                  class="group relative aspect-square cursor-grab overflow-hidden rounded-lg border active:cursor-grabbing"
                >
                  <img :src="img.sm || img.original" alt="" class="size-full object-cover" />
                  <div class="bg-foreground/60 text-background absolute top-1 left-1 flex size-5 items-center justify-center rounded text-[10px] font-bold">
                    {{ reorderImages.indexOf(img) + 1 }}
                  </div>
                </div>
              </div>
              <div class="flex gap-2">
                <Button size="sm" :disabled="savingReorder" @click="saveReorder(post.id)">
                  <Icon v-if="savingReorder" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                  {{ $t('common.save') }}
                </Button>
                <Button size="sm" variant="ghost" @click="cancelReorder">{{ $t('common.cancel') }}</Button>
              </div>
            </div>

            <!-- Normal view -->
            <template v-else>
              <!-- Single image -->
              <img
                v-if="post.post_images?.length === 1"
                :src="post.post_images[0].sm || post.post_images[0].original"
                alt=""
                class="aspect-square w-full object-cover"
              />
              <!-- Multiple images carousel -->
              <div v-else-if="post.post_images?.length > 1" class="relative aspect-square w-full overflow-hidden">
                <img
                  :src="(post.post_images[carouselIndex[post.id] || 0])?.sm || (post.post_images[carouselIndex[post.id] || 0])?.original"
                  alt=""
                  class="size-full object-cover"
                />
                <!-- Carousel controls -->
                <div class="absolute inset-x-0 bottom-0 flex items-center justify-between p-2">
                  <button
                    v-if="(carouselIndex[post.id] || 0) > 0"
                    @click.prevent="carouselIndex[post.id] = (carouselIndex[post.id] || 0) - 1"
                    class="flex size-7 items-center justify-center rounded-full bg-black/50 text-white"
                  >
                    <Icon name="lucide:chevron-left" class="size-4" />
                  </button>
                  <span v-else />
                  <span class="rounded-full bg-black/50 px-2 py-0.5 text-[10px] text-white">
                    {{ (carouselIndex[post.id] || 0) + 1 }} / {{ post.post_images.length }}
                  </span>
                  <button
                    v-if="(carouselIndex[post.id] || 0) < post.post_images.length - 1"
                    @click.prevent="carouselIndex[post.id] = (carouselIndex[post.id] || 0) + 1"
                    class="flex size-7 items-center justify-center rounded-full bg-black/50 text-white"
                  >
                    <Icon name="lucide:chevron-right" class="size-4" />
                  </button>
                  <span v-else />
                </div>
              </div>
              <!-- Legacy single image fallback -->
              <img
                v-else-if="post.post_image"
                :src="post.post_image.sm || post.post_image.original"
                alt=""
                class="aspect-square w-full object-cover"
              />
              <div class="p-4">
                <!-- Edit mode -->
                <template v-if="editingPostId === post.id">
                  <div class="space-y-3">
                    <Textarea v-model="editCaption" rows="2" />
                    <div class="flex gap-2">
                      <Button size="sm" :disabled="updatingPost" @click="updatePost(post.id)">
                        <Icon v-if="updatingPost" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                        {{ $t('common.save') }}
                      </Button>
                      <Button size="sm" variant="ghost" @click="editingPostId = null">{{ $t('common.cancel') }}</Button>
                    </div>
                  </div>
                </template>
                <!-- View mode -->
                <template v-else>
                  <p v-if="post.caption" class="mb-3 text-sm">{{ post.caption }}</p>
                  <p v-else class="text-muted-foreground mb-3 text-sm italic">{{ $t('promotionPosts.noCaption') }}</p>
                  <div class="flex gap-2">
                    <Button
                      size="sm"
                      variant="ghost"
                      @click="
                        editingPostId = post.id;
                        editCaption = post.caption || '';
                      "
                    >
                      <Icon name="hugeicons:edit-02" class="mr-1 size-3.5" />
                      {{ $t('common.edit') }}
                    </Button>
                    <Button
                      v-if="post.post_images?.length > 1"
                      size="sm"
                      variant="ghost"
                      @click="startReorder(post)"
                    >
                      <Icon name="hugeicons:drag-drop" class="mr-1 size-3.5" />
                      {{ $t('promotionPosts.reorder') }}
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
                      {{ $t('common.delete') }}
                    </Button>
                  </div>
                </template>
              </div>
            </template>
          </div>
        </div>

        <div
          v-else
          class="border-border flex flex-col items-center gap-2 rounded-xl border px-4 py-8"
        >
          <div class="bg-muted flex size-10 items-center justify-center rounded-full">
            <Icon name="hugeicons:image-02" class="text-muted-foreground size-5" />
          </div>
          <p class="text-muted-foreground text-sm">{{ $t('promotionPosts.noPostsYet') }}</p>
        </div>
      </div>
    </template>

    <!-- Delete Confirmation Dialog -->
    <Dialog v-model:open="showDeleteDialog">
      <DialogContent class="max-w-sm">
        <DialogHeader>
          <DialogTitle>{{ $t('promotionPosts.deletePost') }}</DialogTitle>
          <DialogDescription>{{ $t('promotionPosts.deleteConfirm') }}</DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <button
            class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
            @click="confirmingDeleteId = null"
          >
            {{ $t('common.cancel') }}
          </button>
          <Button
            variant="destructive"
            :disabled="deletingPostId !== null"
            @click="deletePost(confirmingDeleteId)"
          >
            <Icon v-if="deletingPostId" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
            {{ $t('common.delete') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

<script setup>
import { useSortable } from "@vueuse/integrations/useSortable";
import { toast } from "vue-sonner";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

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
const newPostFiles = ref([]);
const newPostSlots = ref([null]); // Start with 1 slot
const newCaption = ref("");
const creating = ref(false);
const carouselIndex = reactive({});

const hasLastSlotFilled = computed(() => {
  if (newPostFiles.value.length === 0) return false;
  const last = newPostFiles.value[newPostSlots.value.length - 1];
  return last && last.length > 0;
});

const hasAnyNewFile = computed(() =>
  newPostFiles.value.some((f) => f && f.length > 0),
);

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
    carouselIndex[postId] = 0;
    toast.success(t("promotionPosts.reorderSaved"));
  } catch (e) {
    toast.error(e?.data?.message || t("promotionPosts.failedToReorder"));
  } finally {
    savingReorder.value = false;
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
  set: (v) => { if (!v) confirmingDeleteId.value = null; },
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

    // Collect all tmp image IDs from the slots
    const tmpImages = newPostFiles.value
      .flat()
      .filter((f) => f && typeof f === "string" && f.startsWith("tmp-"));
    if (tmpImages.length > 0) {
      body.tmp_post_images = tmpImages;
    }

    const res = await client(apiBase, { method: "POST", body });
    posts.value.unshift(res.data);
    newCaption.value = "";
    newPostFiles.value = [];
    newPostSlots.value = [null];
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
      : t("promotionPosts.title"),
  ),
});
</script>
