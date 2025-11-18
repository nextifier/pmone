<template>
  <NuxtLink
    :to="`/posts/${post.slug}/edit`"
    class="flex items-center gap-x-2 transition hover:opacity-80"
  >
    <div class="bg-muted size-12 shrink-0 overflow-hidden rounded-lg">
      <img
        v-if="post.featured_image"
        :src="
          typeof post.featured_image === 'string'
            ? post.featured_image
            : post.featured_image?.lg || post.featured_image?.original
        "
        :alt="post.title"
        class="size-full object-cover select-none"
        loading="lazy"
      />
    </div>

    <div class="flex flex-col gap-y-0.5 overflow-hidden">
      <p>{{ post.title }}</p>
      <div
        v-if="post.tags?.length"
        class="text-muted-foreground line-clamp-1 text-xs tracking-tight capitalize"
      >
        {{ post.tags.join(", ") }}
      </div>
    </div>
  </NuxtLink>
</template>

<script setup>
defineProps({
  post: {
    type: Object,
    required: true,
  },
});
</script>
