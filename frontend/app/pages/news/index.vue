<template>
  <div id="blog-page" class="min-h-screen-offset pt-4 pb-24 lg:pt-8">
    <div class="container-wider">
      <div class="@container">
        <div
          class="flex flex-col gap-x-6 gap-y-6 lg:flex-row lg:items-end lg:justify-between"
        >
          <h1
            class="text-primary text-4xl font-medium tracking-[-0.06em] sm:text-5xl"
          >
            Latest updates
          </h1>

          <div class="flex w-full max-w-md items-center gap-2">
            <div class="group relative size-full">
              <input
                type="text"
                v-model="searchInput"
                ref="searchInputEl"
                class="input-base peer dark:bg-muted/50! h-9 px-9 py-2 text-sm tracking-tight"
                placeholder="Search posts"
              />

              <Icon name="hugeicons:search-01"
                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400 peer-focus:text-gray-400"
              />

              <span
                id="shortcut-key"
                class="pointer-events-none absolute top-1/2 right-2 hidden -translate-y-1/2 items-center justify-center gap-x-0.5 transition peer-placeholder-shown:flex peer-focus-within:hidden"
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
            v-if="!pending && posts?.length"
            class="text-muted-foreground mb-4 tracking-tight"
          >
            <span v-if="isSearching">
              Showing {{ meta.total }} posts for
              <span class="font-medium text-gray-700 italic dark:text-gray-300"
                >"{{ debouncedSearchInput }}"</span
              >
            </span>
            <span v-else> Showing {{ meta.total }} posts </span>
          </div>

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

          <!-- Search results with no matches (must be before generic "No posts" check) -->
          <div
            v-else-if="isSearching && posts?.length === 0"
            class="flex flex-col gap-y-4"
          >
            <span class="text-4xl font-semibold tracking-tighter sm:text-5xl"
              >No results found for
              <span class="font-bold italic"
                >"{{ debouncedSearchInput }}"</span
              ></span
            >

            <span class="text-base tracking-tight sm:text-lg"
              >Maybe try a different keyword, or explore other topics. We're
              sure you'll find something awesome!</span
            >
          </div>

          <!-- Page out of range (e.g., ?page=10 when max is 9) -->
          <div v-else-if="isPageOutOfRange" class="flex flex-col items-start">
            <h2
              class="text-4xl font-semibold tracking-tighter text-black sm:text-4xl xl:text-5xl dark:text-white"
            >
              Page {{ currentPage }} doesn't exist
            </h2>
            <p
              class="text-muted-foreground mt-4 text-base tracking-tight sm:text-lg"
            >
              There are only {{ meta.last_page }} pages available.
            </p>

            <div class="mt-6 flex items-center gap-3">
              <Button @click="currentPage = meta.last_page">
                Go to last page
              </Button>
              <Button variant="outline" @click="currentPage = 1">
                <IconChevronLeft class="size-4" />
                Back to first page
              </Button>
            </div>
          </div>

          <!-- No posts at all (not searching) -->
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

            <Button as="a" href="/" class="mt-4">
              <IconChevronLeft class="size-4" />
              Back to Home
            </Button>
          </div>

          <!-- Posts list (regular or search results) -->
          <div v-else-if="posts?.length">
            <div
              class="grid grid-cols-1 gap-x-4 gap-y-8 @xl:grid-cols-2 @4xl:grid-cols-12"
              v-auto-animate="{ duration: 300 }"
            >
              <BlogPostCard
                v-for="(post, index) in posts"
                :key="post.id || index"
                :post="post"
                :show-excerpt="false"
                :show-author="false"
                :class="postCardClasses"
              />
            </div>

            <!-- Pagination - show for both regular browsing and search results -->
            <div v-if="meta.last_page > 1" class="mt-12">
              <PaginationCustom
                v-model:page="currentPage"
                :total="meta.total"
                :items-per-page="meta.per_page"
                :sibling-count="1"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
usePageMeta(null, {
  title: "News",
  description:
    "Articles and updates covering events, exhibitions, and industry news.",
});
defineOptions({
  name: "news",
});

const route = useRoute();
const router = useRouter();

const postCardClasses = ref(
  "@xl:first:col-span-2 @4xl:col-span-4 @4xl:first:col-span-6 @4xl:nth-2:col-span-6 @5xl:col-span-3 @[90rem]:col-span-3 @7xl:[&:nth-child(-n+3)]:col-span-4",
);

const postStore = usePostStore();
const { posts, pending, error, meta } = storeToRefs(postStore);

// URL-driven pagination (source of truth)
const currentPage = computed({
  get: () => Number(route.query.page) || 1,
  set: (val) => {
    const query = { ...route.query };
    if (val > 1) {
      query.page = String(val);
    } else {
      delete query.page;
    }
    router.push({ query });
  },
});

// Search state
const initialSearchQuery = (route.query.q || "").toString();
const searchInput = ref(initialSearchQuery);
const debouncedSearchInput = refDebounced(searchInput, 400);
const isSearching = computed(() => debouncedSearchInput.value.length > 0);
const isPageOutOfRange = computed(
  () =>
    !isSearching.value &&
    currentPage.value > 1 &&
    meta.value.last_page >= 1 &&
    currentPage.value > meta.value.last_page,
);

// Initial SSR fetch
const initialPage = Number(route.query.page) || 1;
if (initialSearchQuery) {
  await postStore.searchPosts(initialSearchQuery, { page: initialPage });
} else {
  await postStore.fetchPosts({ page: initialPage, force: true });
}

// Search input changes -> update URL (reset to page 1)
watch(debouncedSearchInput, (newSearchTerm) => {
  const currentQ = (route.query.q || "").toString();
  const trimmed = newSearchTerm.trim();

  // Skip if URL already matches (prevents loop from back/forward sync)
  if (trimmed === currentQ) return;

  const query = {};
  if (trimmed) {
    query.q = trimmed;
  }
  router.push({ query });
});

// Route query changes -> fetch data
// Handles: pagination clicks, search, browser back/forward
watch(
  () => ({ page: route.query.page, q: route.query.q }),
  async (newQuery, oldQuery) => {
    const newPage = Number(newQuery.page) || 1;
    const newSearch = (newQuery.q || "").toString();
    const oldPage = Number(oldQuery?.page) || 1;
    const oldSearch = (oldQuery?.q || "").toString();

    // Skip if nothing changed
    if (newPage === oldPage && newSearch === oldSearch) return;

    // Sync search input with URL (for browser back/forward)
    if (newSearch !== searchInput.value) {
      searchInput.value = newSearch;
    }

    // Fetch data based on current URL state
    if (newSearch) {
      await postStore.searchPosts(newSearch, { page: newPage });
    } else {
      await postStore.fetchPosts({ page: newPage, force: true });
    }

    window.scrollTo({ top: 0 });
  },
);

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
