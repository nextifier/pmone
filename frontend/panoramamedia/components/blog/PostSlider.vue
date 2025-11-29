<template>
  <div v-if="filteredPosts?.length" class="space-y-4 sm:space-y-6">
    <div class="container">
      <h2 class="section-title">{{ headline }}</h2>
    </div>

    <Carousel
      v-if="filteredPosts?.length"
      v-slot="{ scrollPrev, scrollNext, canScrollPrev, canScrollNext }"
      class="focusable relative overflow-hidden"
      :opts="{
        loop: false,
        align: 'center',
        dragFree: false,
        skipSnaps: true,
      }"
      :plugins="[$wheelGesturesPlugin()]"
    >
      <CarouselContent class="carousel-mx -ml-2 *:select-none">
        <CarouselItem
          v-for="(post, index) in filteredPosts.slice(0, 20)"
          :key="index"
          class="carousel-item basis-[280px] pl-2 lg:basis-[320px]"
        >
          <BlogPostCard :post="post" />
        </CarouselItem>
      </CarouselContent>

      <div class="mt-6 h-8">
        <div
          v-if="canScrollPrev || canScrollNext"
          class="container flex h-full justify-end gap-2"
        >
          <button
            @click="scrollPrev"
            :disabled="!canScrollPrev"
            class="bg-muted hover:bg-border text-primary flex aspect-square h-full items-center justify-center rounded-md transition active:scale-98"
            aria-label="previous"
          >
            <Icon name="lucide:arrow-left" class="size-4" />
          </button>

          <button
            @click="scrollNext"
            :disabled="!canScrollNext"
            class="bg-muted hover:bg-border text-primary flex aspect-square h-full items-center justify-center rounded-md transition active:scale-98"
            aria-label="next"
          >
            <Icon name="lucide:arrow-right" class="size-4" />
          </button>

          <nuxt-link
            to="/news"
            class="text-primary hover:bg-primary hover:text-primary-foreground flex h-full items-center justify-center rounded-md border px-4 text-sm font-semibold tracking-tight transition active:scale-98"
          >
            <span>View all</span>
          </nuxt-link>
        </div>
      </div>
    </Carousel>
  </div>
</template>

<script setup>
const props = defineProps({
  headline: {
    type: String,
    default: "Latest updates",
  },
});
const route = useRoute();

// const postStore = usePostStore();
// postStore.fetchPosts();

const config = useAppConfig();

const { data } = await useFetch(
  `${config.app.pmOneApiUrl}/api/public/blog/posts`,
  {
    headers: {
      Authorization: `Bearer ${config.app.pmOneApiKey}`,
      Accept: "application/json",
    },
    query: {
      per_page: 100,
      sort: "published_at",
      order: "desc",
    },
    key: "posts-slider",
    server: false,
  },
);

const filteredPosts = computed(() => {
  const posts = Array.isArray(data?.value?.data) ? data?.value?.data : [];
  return route?.params?.slug
    ? posts.filter((post) => post.slug !== route.params.slug)
    : posts;
});
</script>
