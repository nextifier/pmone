<template>
  <div class="min-h-screen-offset mx-auto flex max-w-2xl flex-col space-y-6 pt-6 pb-20">
    <div class="flex items-center justify-between gap-2">
      <BackButton destination="/posts" />
    </div>

    <div class="space-y-1">
      <h1 class="page-title">Edit Post</h1>
      <p class="page-description">Update your blog post.</p>
    </div>

    <div v-if="loading" class="py-12 text-center">
      <Spinner class="mx-auto" />
      <p class="text-muted-foreground mt-4">Loading post...</p>
    </div>

    <PostForm
      v-else-if="post"
      mode="edit"
      :initial-data="post"
      :post-slug="postSlug"
      @cancel="navigateTo('/posts')"
      @success="handleSuccess"
    />

    <div v-else class="py-12 text-center">
      <p class="text-muted-foreground">Post not found</p>
      <button
        @click="navigateTo('/posts')"
        class="text-primary hover:text-primary/80 mt-4 underline"
      >
        Back to Posts
      </button>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("posts");

const route = useRoute();
const postSlug = route.params.slug;
const client = useSanctumClient();

const post = ref(null);
const loading = ref(true);

onMounted(async () => {
  await loadPost();
});

async function loadPost() {
  loading.value = true;

  try {
    const response = await client(`/api/posts/${postSlug}?for=edit`);
    post.value = response.data;

    // Set page title to post title
    if (post.value?.title) {
      useHead({
        title: `Edit: ${post.value.title}`,
      });
    }
  } catch (error) {
    console.error("Failed to load post:", error);
    toast.error("Failed to load post");
  } finally {
    loading.value = false;
  }
}

async function handleSuccess() {
  const needsRefresh = useState('posts-needs-refresh', () => false);
  needsRefresh.value = true;
  await navigateTo("/posts");
}
</script>
