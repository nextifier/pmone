<template>
  <div class="flex flex-col gap-y-6">
    <div class="flex items-start justify-between gap-x-2">
      <div class="space-y-1">
        <h3 class="page-title">Promotion Posts</h3>
        <p class="page-description">Marketing materials for this brand.</p>
      </div>
      <Button @click="showAdd = true" size="sm" class="shrink-0">
        <Icon name="hugeicons:add-01" class="size-4" />
        Add Post
      </Button>
    </div>

    <form @submit.prevent="saveLimit" class="flex items-end gap-2">
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
          <a
            v-for="(img, idx) in getAllImages(post)"
            :key="idx"
            :href="img.url || img.original"
            target="_blank"
            class="group relative aspect-square overflow-hidden rounded-lg"
          >
            <img :src="img.md || img.url" class="size-full object-cover transition-opacity group-hover:opacity-90" />
          </a>
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
              @click="downloadPostImages(post)"
            >
              <Icon name="lucide:download" class="size-3.5" />
              Download All Images
            </Button>
            <Button variant="ghost" size="sm" class="text-muted-foreground" @click="deletePost(post.id)">
              <Icon name="hugeicons:delete-02" class="size-3.5" />
              Delete
            </Button>
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
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

const props = defineProps({ brandEvent: Object });
const emit = defineEmits(["refresh"]);
const route = useRoute();
const client = useSanctumClient();
const showAdd = ref(false);
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

function getAllImages(post) {
  if (post.post_images?.length) return post.post_images;
  if (post.post_image) return [post.post_image];
  return [];
}

async function copyCaption(caption) {
  try {
    await navigator.clipboard.writeText(caption);
    // Find the post with this caption to show feedback
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

async function downloadPostImages(post) {
  const images = post.post_images?.length
    ? post.post_images
    : post.post_image
      ? [post.post_image]
      : [];
  if (!images.length) return;

  try {
    for (const img of images) {
      const blob = await client(`/api/media/${img.id}/download`, { responseType: "blob" });
      const blobUrl = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = blobUrl;
      link.download = img.file_name || `promo-${post.id}-${img.id}.jpg`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(blobUrl);
    }
    toast.success("Download complete");
  } catch (e) {
    toast.error("Failed to download");
  }
}

async function deletePost(postId) {
  try {
    await client(`${apiUrl.value}/${postId}`, { method: "DELETE" });
    toast.success("Post deleted");
    refresh();
  } catch (e) {
    toast.error("Failed to delete");
  }
}
</script>
