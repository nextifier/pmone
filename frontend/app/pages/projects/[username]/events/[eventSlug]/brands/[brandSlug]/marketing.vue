<template>
  <div class="flex flex-col gap-y-6">
    <div class="flex items-center justify-between">
      <div class="space-y-1">
        <h4 class="font-semibold tracking-tight">Promotion Posts</h4>
        <p class="text-muted-foreground text-sm tracking-tight">
          Marketing materials for this brand.
        </p>
      </div>
      <Button @click="showAdd = true" size="sm">
        <Icon name="hugeicons:add-01" class="size-4" />
        Add Post
      </Button>
    </div>

    <div v-if="pending" class="flex items-center justify-center py-10">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <div v-else-if="posts?.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="post in posts"
        :key="post.id"
        class="border-border overflow-hidden rounded-xl border"
      >
        <div v-if="post.post_images?.length" class="aspect-square overflow-hidden">
          <img
            v-if="post.post_images.length === 1"
            :src="post.post_images[0]?.md || post.post_images[0]?.url"
            class="size-full object-cover"
          />
          <div v-else class="relative size-full">
            <img
              :src="(post.post_images[carouselIdx[post.id] || 0])?.md || (post.post_images[carouselIdx[post.id] || 0])?.url"
              class="size-full object-cover"
            />
            <div class="absolute inset-x-0 bottom-0 flex items-center justify-between p-1.5">
              <button
                v-if="(carouselIdx[post.id] || 0) > 0"
                @click.prevent="carouselIdx[post.id] = (carouselIdx[post.id] || 0) - 1"
                class="flex size-6 items-center justify-center rounded-full bg-black/50 text-white"
              >
                <Icon name="lucide:chevron-left" class="size-3.5" />
              </button>
              <span v-else />
              <span class="rounded-full bg-black/50 px-1.5 py-0.5 text-[10px] text-white">
                {{ (carouselIdx[post.id] || 0) + 1 }}/{{ post.post_images.length }}
              </span>
              <button
                v-if="(carouselIdx[post.id] || 0) < post.post_images.length - 1"
                @click.prevent="carouselIdx[post.id] = (carouselIdx[post.id] || 0) + 1"
                class="flex size-6 items-center justify-center rounded-full bg-black/50 text-white"
              >
                <Icon name="lucide:chevron-right" class="size-3.5" />
              </button>
              <span v-else />
            </div>
          </div>
        </div>
        <div v-else-if="post.post_image" class="aspect-square">
          <img :src="post.post_image?.md || post.post_image?.url" class="size-full object-cover" />
        </div>
        <div class="p-3">
          <p class="text-sm">{{ post.caption || "No caption" }}</p>
          <div class="mt-2 flex items-center gap-1">
            <ButtonCopy v-if="post.caption" :text="post.caption" />
            <button
              v-tippy="'Download images'"
              class="text-muted-foreground hover:text-foreground flex size-7 items-center justify-center rounded-lg"
              @click="downloadPostImages(post)"
            >
              <Icon name="lucide:download" class="size-3.5" />
            </button>
            <Button variant="ghost" size="sm" class="size-7 p-0" @click="deletePost(post.id)">
              <Icon name="hugeicons:delete-02" class="size-4" />
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
          <p class="text-muted-foreground max-w-md text-sm">No promotion posts yet. Add your first post to start marketing.</p>
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

const apiUrl = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${route.params.brandSlug}/promotion-posts`
);
const data = ref(null);
const pending = ref(true);
const posts = computed(() => data.value?.data || []);
const carouselIdx = reactive({});

async function refresh() {
  pending.value = true;
  try {
    data.value = await client(apiUrl.value);
  } catch (e) {}
  pending.value = false;
}

onMounted(() => refresh());

async function downloadImage(url, filename) {
  const response = await fetch(url);
  const blob = await response.blob();
  const blobUrl = window.URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = blobUrl;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  window.URL.revokeObjectURL(blobUrl);
}

async function downloadPostImages(post) {
  const images = post.post_images?.length ? post.post_images : post.post_image ? [post.post_image] : [];
  if (!images.length) return;

  try {
    for (let i = 0; i < images.length; i++) {
      const img = images[i];
      const url = img.original || img.xl || img.lg || img.url;
      const ext = url.split(".").pop()?.split("?")[0] || "jpg";
      const filename = `promo-${post.id}-${i + 1}.${ext}`;
      await downloadImage(url, filename);
    }
    toast.success("Download started");
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
