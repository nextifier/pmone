<template>
  <div v-if="filteredPosts.length > 0" class="space-y-4 lg:space-y-6">
    <h5
      class="text-primary text-3xl !leading-[1.25] font-semibold tracking-[-0.06em] text-balance sm:text-5xl"
    >
      You might also like
    </h5>
    <div
      class="grid grid-cols-2 gap-x-2 gap-y-6 sm:grid-cols-[repeat(auto-fit,minmax(240px,1fr))]"
    >
      <nuxt-link
        v-for="post in filteredPosts.slice(0, 20)"
        :key="post.slug"
        :to="`/news/${post.slug}`"
        class="flex flex-col gap-y-1.5"
      >
        <div
          class="bg-muted aspect-[20/19] w-full shrink-0 overflow-hidden rounded-lg lg:aspect-[16/9]"
        >
          <NuxtImg
            v-if="post.featured_image"
            :src="post.featured_image?.medium || post.featured_image?.original || post.featured_image"
            :alt="post.title"
            class="size-full object-cover"
            loading="lazy"
            sizes="200px lg:400px"
            format="webp"
          />
        </div>

        <div class="flex flex-col items-start gap-y-0.5 text-left">
          <h6
            class="text-primary line-clamp-3 text-sm leading-snug font-semibold tracking-tight lg:text-base"
            v-tippy="{
              content: post.title,
              delay: [600, 100],
            }"
          >
            {{ post.title }}
          </h6>

          <div
            class="text-muted-foreground flex items-center gap-x-3 text-xs tracking-tight"
          >
            <span
              v-if="post.published_at"
              v-tippy="
                $dayjs(post.published_at).format('MMMM D, YYYY [at] h:mm A')
              "
            >
              {{ $dayjs(post.published_at).fromNow() }}
            </span>
          </div>
        </div>
      </nuxt-link>
    </div>
  </div>
</template>

<script setup>
const route = useRoute();
const { $dayjs } = useNuxtApp();

const postStore = usePostStore();
postStore.fetchPosts();
const filteredPosts = computed(() => {
  return postStore.posts.filter((post) => post.slug !== route.params.slug);
});
</script>
