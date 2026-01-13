<template>
  <div class="min-h-screen-offset mx-auto flex max-w-2xl flex-col space-y-6 pt-6 pb-20">
    <div class="flex items-center justify-between gap-2">
      <BackButton destination="/posts" />
    </div>

    <div class="space-y-1">
      <h1 class="page-title">Create New Post</h1>
      <p class="page-description">Write and publish a new blog post.</p>
    </div>

    <PostEditor mode="create" @cancel="navigateTo('/posts')" @success="handleSuccess" />
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["posts.create"],
  layout: "post-editor",
});

usePageMeta("posts");

const { signalRefresh } = useDataRefresh();

async function handleSuccess() {
  signalRefresh("posts-list");
  await navigateTo("/posts");
}
</script>
