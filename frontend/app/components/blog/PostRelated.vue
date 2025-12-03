<template>
  <div v-if="!pending && filteredPosts.length > 0">
    <!-- Sidebar variant (compact list) -->
    <div v-if="variant === 'sidebar'" class="grid grid-cols-1 gap-y-2.5 self-start">
      <h5 class="text-primary text-sm font-semibold tracking-tighter">
        {{ title }}
      </h5>
      <div class="grid grid-cols-1 gap-y-2.5">
        <nuxt-link
          v-for="post in filteredPosts.slice(0, limit)"
          :key="post.slug"
          :to="`/news/${post.slug}`"
          class="flex items-center gap-x-2"
          @click="onLinkClick"
        >
          <div
            class="bg-muted border-border size-16 shrink-0 overflow-hidden rounded-lg border"
          >
            <NuxtImg
              v-if="post.featured_image"
              :src="
                post.featured_image?.thumb ||
                post.featured_image?.medium ||
                post.featured_image?.original ||
                post.featured_image
              "
              :alt="post.title"
              class="size-full object-cover"
              loading="lazy"
              sizes="64px"
              format="webp"
            />
          </div>

          <div class="flex flex-col items-start gap-y-0.5 text-left">
            <h6
              class="text-primary line-clamp-2 text-sm font-semibold tracking-tight"
              v-tippy="{
                content: post.title,
                delay: [600, 100],
              }"
            >
              {{ post.title }}
            </h6>

            <div class="text-muted-foreground flex items-center gap-x-3 text-xs tracking-tight">
              <span
                v-if="post.published_at"
                v-tippy="$dayjs(post.published_at).format('MMMM D, YYYY [at] h:mm A')"
              >
                {{ $dayjs(post.published_at).fromNow() }}
              </span>
            </div>
          </div>
        </nuxt-link>
      </div>
    </div>

    <!-- Grid variant (large cards) -->
    <div v-else class="space-y-4 lg:space-y-6">
      <h5
        class="text-primary text-3xl !leading-[1.25] font-semibold tracking-[-0.06em] text-balance sm:text-5xl"
      >
        {{ title }}
      </h5>
      <div
        class="grid grid-cols-2 gap-x-2 gap-y-6 sm:grid-cols-[repeat(auto-fit,minmax(240px,1fr))]"
      >
        <nuxt-link
          v-for="post in filteredPosts.slice(0, limit)"
          :key="post.slug"
          :to="`/news/${post.slug}`"
          class="flex flex-col gap-y-1.5"
        >
          <div
            class="bg-muted aspect-[20/19] w-full shrink-0 overflow-hidden rounded-lg lg:aspect-[16/9]"
          >
            <NuxtImg
              v-if="post.featured_image"
              :src="
                post.featured_image?.medium ||
                post.featured_image?.original ||
                post.featured_image
              "
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

            <div class="text-muted-foreground flex items-center gap-x-3 text-xs tracking-tight">
              <span
                v-if="post.published_at"
                v-tippy="$dayjs(post.published_at).format('MMMM D, YYYY [at] h:mm A')"
              >
                {{ $dayjs(post.published_at).fromNow() }}
              </span>
            </div>
          </div>
        </nuxt-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useSidebar } from "@/components/ui/sidebar/utils";

const props = defineProps({
  variant: {
    type: String,
    default: "grid",
    validator: (value) => ["grid", "sidebar"].includes(value),
  },
  title: {
    type: String,
    default: "You might also like",
  },
  limit: {
    type: Number,
    default: 20,
  },
});

const { setOpenMobile } = useSidebar();
const route = useRoute();
const { $dayjs } = useNuxtApp();

// Use lazy fetch to avoid hydration mismatch in lazy-loaded component
const { data: postsData, pending } = useLazyFetch("/api/blog/posts", {
  query: {
    per_page: props.limit + 1,
    sort: "-published_at",
  },
  key: "post-related-posts",
});

const filteredPosts = computed(() => {
  const posts = postsData.value?.data || [];
  return posts.filter((post) => post.slug !== route.params.slug);
});

const onLinkClick = () => {
  if (props.variant === "sidebar") {
    setOpenMobile(false);
  }
};
</script>
