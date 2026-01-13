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

  <div v-else class="py-20 text-center">
    <Icon name="hugeicons:file-not-found" class="text-muted-foreground mx-auto mb-4 size-12" />
    <p class="text-muted-foreground mb-4">Post not found</p>
    <NuxtLink
      to="/posts"
      class="text-primary hover:text-primary/80 text-sm underline"
    >
      Back to Posts
    </NuxtLink>
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
