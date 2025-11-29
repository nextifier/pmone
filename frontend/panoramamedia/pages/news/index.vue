<template>
  <div id="blog-page" class="min-h-screen-offset pt-4 pb-8 lg:pt-8 lg:pb-24">
    <div class="container-wider">
      <div class="@container">
        <div
          class="flex flex-col gap-x-6 gap-y-6 lg:flex-row lg:items-end lg:justify-between"
        >
          <h1
            class="text-primary text-4xl font-semibold tracking-[-0.06em] sm:text-6xl"
          >
            Latest updates
          </h1>

          <div class="flex w-full max-w-md items-center gap-2">
            <div class="group relative size-full">
              <input
                type="text"
                v-model="searchInput"
                ref="searchInputEl"
                class="input-base peer h-10 px-9 py-2 text-sm tracking-tight"
                placeholder="Search posts"
              />

              <IconSearch
                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400 peer-focus:text-gray-400"
              />

              <span
                id="shortcut-key"
                class="pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 items-center justify-center gap-x-0.5 transition peer-placeholder-shown:flex peer-focus-within:hidden"
              >
                <kbd class="keyboard-symbol">{{ metaSymbol }} K</kbd>
              </span>

              <button
                id="clear-input"
                type="button"
                @click="
                  searchInput = '';
                  $refs.searchInputEl.focus();
                "
                class="absolute top-1/2 right-3 flex size-6 -translate-y-1/2 items-center justify-center rounded-full bg-gray-100 transition-colors peer-placeholder-shown:hidden hover:bg-gray-200 dark:bg-gray-900 dark:hover:bg-gray-800"
              >
                <IconClose class="h-3" />
              </button>
            </div>
          </div>
        </div>

        <div class="mt-8 sm:mt-10">
          <div
            v-if="pending"
            class="grid grid-cols-1 gap-x-4 gap-y-8 @xl:grid-cols-2 @4xl:grid-cols-12"
          >
            <BlogPostCardSkeleton
              v-for="(_, index) in 16"
              :key="index"
              :class="postCardClasses"
            />
          </div>

          <div
            v-else-if="error"
            class="flex items-center justify-center text-center"
          >
            <span class="text-primary text-2xl font-semibold tracking-tighter"
              >Failed to get the data.</span
            >
          </div>

          <div
            v-else-if="posts?.length === 0"
            class="flex flex-col items-start"
          >
            <h2
              class="text-4xl font-bold tracking-tighter text-black sm:text-4xl xl:text-5xl dark:text-white"
            >
              No posts yet
            </h2>
            <p class="mt-4 text-base tracking-tight sm:text-lg">
              Please come back later
            </p>

            <nuxt-link
              to="/"
              class="mt-4 flex items-center gap-x-1 rounded-full bg-black p-4 font-semibold tracking-tight text-white dark:bg-white dark:text-black"
            >
              <IconChevronLeft class="h-4" />
              <span>Back to Home</span>
            </nuxt-link>
          </div>

          <div v-else-if="posts?.length">
            <div
              v-if="filteredPosts?.length"
              class="grid grid-cols-1 gap-x-4 gap-y-8 @xl:grid-cols-2 @4xl:grid-cols-12"
              v-auto-animate="{ duration: 300 }"
            >
              <BlogPostCard
                v-for="(post, index) in filteredPosts"
                :key="index"
                :post="post"
                :show-excerpt="false"
                :show-author="false"
                :class="postCardClasses"
              />
            </div>

            <div v-else class="flex flex-col gap-y-4">
              <span class="text-4xl font-semibold tracking-tighter sm:text-5xl"
                >No results found for
                <span class="font-bold italic">{{ searchInput }}.</span></span
              >

              <span class="text-base tracking-tight sm:text-lg"
                >Maybe try a different keyword, or explore other topics—we’re
                sure you’ll find something awesome!</span
              >
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
usePageMeta("news");
defineOptions({
  name: "news",
});

const postCardClasses = ref(
  "@xl:first:col-span-2 @4xl:col-span-4 @4xl:first:col-span-6 @4xl:nth-2:col-span-6 @5xl:col-span-3 @[90rem]:col-span-3 @7xl:[&:nth-child(-n+3)]:col-span-4",
);

const postStore = usePostStore();
const { posts, pending, error } = storeToRefs(postStore);
postStore.fetchPosts();

const searchInput = defineModel({ default: "" });
const debouncedSearchInput = refDebounced(searchInput, 200);

const filteredPosts = computed(() => {
  if (!posts.value) return [];
  return posts.value.filter((post) => {
    return (
      post.title
        .toLowerCase()
        .includes(debouncedSearchInput.value.toLowerCase()) ||
      post.primary_tag?.name
        ?.toString()
        .toLowerCase()
        .includes(debouncedSearchInput.value.toLowerCase())
    );
  });
});

const searchInputEl = ref();
const { metaSymbol } = useShortcuts();
defineShortcuts({
  meta_k: {
    handler: async () => {
      searchInputEl.value?.focus();
    },
  },
});
</script>
