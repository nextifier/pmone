<template>
  <div class="@container flex flex-col gap-y-2 select-none">
    <nuxt-link
      :to="`/news/${post.slug}`"
      class="border-border relative flex aspect-16/10 items-center justify-center overflow-hidden rounded-lg border"
      @click="active = post.slug"
    >
      <NuxtImg
        v-if="post.featured_image"
        :src="post.featured_image?.medium || post.featured_image?.original || post.featured_image"
        :alt="post.title"
        class="h-full w-full object-cover"
        :style="imageStyle"
        loading="lazy"
        sizes="100vw sm:600px"
        width="479"
        height="269"
        format="webp"
      />

      <Logo v-else class="text-primary w-[50%] opacity-50" />

      <div
        class="pointer-events-none absolute inset-x-0 bottom-0 flex h-20 w-full items-end justify-between px-3 pb-2.5 text-xs font-semibold tracking-tight select-none"
        :class="{
          'bg-linear-to-t from-black/60 to-transparent text-white':
            post.featured_image,
          '': !post.featured_image,
        }"
      >
        <span v-if="post.tags?.length > 0" class="capitalize">
          {{ post.tags[0] }}
        </span>
        <span v-else></span>

        <span v-if="post.reading_time">
          <span class="font-normal"
            >{{ post.reading_time }} min<span v-if="post.reading_time > 1"
              >s</span
            >
            read
          </span>
        </span>
        <span v-else></span>
      </div>
    </nuxt-link>

    <div class="flex w-full flex-col items-start px-1">
      <nuxt-link
        :to="`/news/${post.slug}`"
        class="text-primary text-lg !leading-snug font-semibold tracking-[-0.04em] transition duration-300 lg:line-clamp-4 @sm:text-xl @lg:text-2xl"
        v-tippy="post.title"
        @click.native="active = post.slug"
        >{{ post.title }}</nuxt-link
      >

      <p
        v-if="
          useAppConfig()?.settings?.blog?.showPostCardExcerpt &&
          post.excerpt
        "
        class="mt-2 text-sm tracking-tight"
      >
        {{ post.excerpt }}
      </p>

      <div class="mt-2 flex w-full items-center justify-between gap-x-3">
        <div
          v-if="
            useAppConfig()?.settings?.blog?.showPostCardAuthor &&
            post.authors?.length
          "
          class="flex items-center gap-x-1.5 text-left"
        >
          <div class="flex shrink-0 -space-x-4">
            <nuxt-link
              v-for="(author, index) in post.authors"
              :key="index"
              :to="author.website ?? ''"
              target="_blank"
              class="relative rounded-full"
              :style="`z-index: ${post.authors.length - index}`"
            >
              <div
                class="border-border bg-muted flex size-8 items-center justify-center overflow-hidden rounded-full border"
              >
                <NuxtImg
                  v-if="author.profile_image"
                  :src="author.profile_image"
                  class="size-full object-cover"
                  width="36"
                  height="36"
                  sizes="36px"
                  loading="lazy"
                  format="webp"
                />
              </div>
            </nuxt-link>
          </div>

          <div class="flex flex-col gap-y-1">
            <div
              class="text-primary line-clamp-1 text-sm font-semibold tracking-tight"
            >
              <nuxt-link
                v-for="(author, index) in post.authors"
                :key="index"
                :to="author.website ?? ''"
                target="_blank"
                class="hover:underline"
              >
                {{ author.name
                }}<span v-if="index != Object.keys(post.authors).length - 1"
                  >,
                </span>
              </nuxt-link>
            </div>
          </div>
        </div>

        <span
          v-if="post.published_at"
          v-tippy="$dayjs(post.published_at).format('MMMM D, YYYY [at] h:mm A')"
          class="text-muted-foreground line-clamp-1 shrink-0 text-xs tracking-tight"
        >
          {{ $dayjs(post.published_at).fromNow() }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  post: Object,
});

const { $dayjs } = useNuxtApp();

const active = useState("active-post-slug", () => null);

const imageStyle = computed(() => {
  if (active.value === props.post.slug) {
    return { "view-transition-name": `post-feature-img-${props.post.slug}` };
  }
  return {};
});
</script>
