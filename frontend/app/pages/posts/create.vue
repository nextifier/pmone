<template>
  <div class="container mx-auto max-w-5xl px-4 py-8">
    <div class="mb-8">
      <h1 class="text-3xl font-semibold">Create New Post</h1>
      <p class="text-muted-foreground mt-2">Write and publish a new blog post</p>
    </div>

    <PostForm mode="create" @cancel="navigateTo('/posts')" @success="handleSuccess" />
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("posts");

async function handleSuccess() {
  await navigateTo("/posts");
}
</script>
