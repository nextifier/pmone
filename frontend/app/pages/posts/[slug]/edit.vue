<template>
  <div class="min-h-screen-offset mx-auto flex max-w-2xl flex-col space-y-6 pt-6 pb-20">
    <div class="flex items-center justify-between gap-2">
      <BackButton destination="/posts" />
    </div>

    <div class="space-y-1">
      <h1 class="page-title">Edit Post</h1>
      <p class="page-description">Update your blog post.</p>
    </div>

    <LoadingState v-if="loading" label="Loading post.." />

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

const {
  data: postResponse,
  pending: loading,
  error: fetchError,
  refresh: loadPost,
} = await useLazySanctumFetch(() => `/api/posts/${postSlug}?for=edit`, {
  key: `post-edit-${postSlug}`,
});

const post = computed(() => postResponse.value?.data || null);

const error = computed(() => {
  if (!fetchError.value) return null;
  if (fetchError.value.statusCode === 404) return "Post not found";
  if (fetchError.value.statusCode === 403) return "You do not have permission";
  return fetchError.value.message || "Failed to load post";
});

// Set page title to post title
watchEffect(() => {
  if (post.value?.title) {
    useHead({
      title: `Edit: ${post.value.title}`,
    });
  }
});

const { signalRefresh } = useDataRefresh();

async function handleSuccess() {
  signalRefresh("posts-list");
  await navigateTo("/posts");
}
</script>
