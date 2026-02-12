<template>
  <PostEditor mode="create" @cancel="navigateTo('/posts')" @success="handleSuccess">
    <PostEditorContent />
  </PostEditor>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["posts.create"],
  layout: "app",
});

usePageMeta(null, {
  title: "Create New Post",
});

const { signalRefresh } = useDataRefresh();

async function handleSuccess() {
  signalRefresh("posts-list");
  await navigateTo("/posts");
}
</script>
