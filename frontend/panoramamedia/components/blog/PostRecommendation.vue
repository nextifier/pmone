<template>
  <div
    v-if="filteredPosts.length > 0"
    class="grid grid-cols-1 gap-y-2.5 self-start"
  >
    <h5 class="text-primary text-sm font-semibold tracking-tighter">
      Editor's Picks
    </h5>
    <div class="grid grid-cols-1 gap-y-2.5">
      <nuxt-link
        v-for="post in filteredPosts.slice(0, 20)"
        :key="post.slug"
        :to="`/news/${post.slug}`"
        class="flex items-center gap-x-2"
        @click="setOpenMobile(false)"
      >
        <div
          class="bg-muted border-border size-16 shrink-0 overflow-hidden rounded-lg border"
        >
          <NuxtImg
            v-if="post.featured_image"
            :src="post.featured_image?.thumb || post.featured_image?.medium || post.featured_image?.original || post.featured_image"
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
import { useSidebar } from "@/components/ui/sidebar/utils";
const { setOpenMobile } = useSidebar();

const route = useRoute();
const { $dayjs } = useNuxtApp();

const postStore = usePostStore();
postStore.fetchPosts();
const filteredPosts = computed(() => {
  return postStore.posts.filter((post) => post.slug !== route.params.slug);
});
</script>
