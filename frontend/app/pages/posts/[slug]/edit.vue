<template>
  <LoadingState v-if="loading" label="Loading post..." class="py-20" />

  <PostEditor
    v-else-if="post"
    mode="edit"
    :initial-data="post"
    :post-slug="postSlug"
    @cancel="navigateTo('/posts')"
    @success="handleSuccess"
  >
    <PostEditorContent />
  </PostEditor>

  <div v-else class="flex w-full flex-col items-center justify-center gap-y-3 text-center">
    <div class="bg-muted flex size-12 items-center justify-center rounded-full">
      <Icon name="hugeicons:file-not-found" class="text-primary size-6" />
    </div>
    <p class="text-primary text-primary text-4xl font-medium tracking-tighter">Post not found.</p>
    <button
      @click="navigateTo('/posts')"
      class="bg-primary text-primary-foreground hover:bg-primary/80 mt-2 flex items-center justify-center gap-x-1 rounded-lg px-4 py-2 font-medium tracking-tight"
    >
      <Icon name="lucide:arrow-left" class="size-5" />
      <span>Back to Posts</span>
    </button>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["posts.update"],
  layout: "post-editor",
});

const route = useRoute();
const postSlug = route.params.slug;

const {
  data: postResponse,
  pending: loading,
  error: fetchError,
} = await useLazySanctumFetch(() => `/api/posts/${postSlug}?for=edit`, {
  key: `post-edit-${postSlug}`,
});

const post = computed(() => postResponse.value?.data || null);

// Set page title dynamically
watchEffect(() => {
  if (post.value?.title) {
    useHead({
      title: `Edit: ${post.value.title}`,
    });
  } else {
    useHead({
      title: "Edit Post",
    });
  }
});

const { signalRefresh } = useDataRefresh();

async function handleSuccess(data) {
  signalRefresh("posts-list");
  // If post was deleted (data is null), go back to list
  if (data === null) {
    await navigateTo("/posts");
  } else {
    await navigateTo("/posts");
  }
}
</script>
