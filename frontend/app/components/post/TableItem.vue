<template>
  <NuxtLink
    :to="`/posts/${post.slug}/edit`"
    class="flex items-center gap-x-2 transition hover:opacity-80"
  >
    <div class="bg-muted border-border size-12 shrink-0 overflow-hidden rounded-lg border">
      <img
        v-if="post.featured_image"
        :src="
          typeof post.featured_image === 'string'
            ? post.featured_image
            : post.featured_image?.sm || post.featured_image?.original
        "
        :alt="post.title"
        class="size-full object-cover select-none"
        loading="lazy"
      />
    </div>

    <div class="flex flex-col items-start gap-y-0.5 overflow-hidden">
      <div class="flex items-center gap-x-2">
        <span
          v-if="post.status"
          class="text-xs font-medium tracking-tight capitalize"
          :class="{
            'text-success-foreground': post.status.toLowerCase() === 'published',
            'text-warning-foreground': post.status.toLowerCase() === 'draft',
            'text-muted-foreground': post.status.toLowerCase() === 'scheduled',
          }"
          >{{ post.status }}</span
        >
        <span
          v-if="post.visibility === 'private'"
          class="bg-muted text-muted-foreground rounded-full px-1.5 py-0.5 text-xs font-medium tracking-tight capitalize"
          >Private</span
        >
        <DevOnly>
          <span class="text-muted-foreground text-xs font-medium">ID: {{ post.id }}</span>
        </DevOnly>
      </div>
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
