<template>
  <div id="blog-page" class="min-h-screen-offset pt-4 pb-24 lg:pt-8">
    <div class="container-wider">
      <div class="@container">
        <div class="flex flex-col gap-x-6 gap-y-6 lg:flex-row lg:items-end lg:justify-between">
          <h1 class="text-primary text-4xl font-medium tracking-[-0.06em] sm:text-5xl">
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

              <IconSearch
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
          <div v-if="!pending && posts?.length" class="text-muted-foreground mb-4 tracking-tight">
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
            <BlogPostCardSkeleton v-for="(_, index) in 16" :key="index" :class="postCardClasses" />
          </div>

          <div v-else-if="error" class="flex items-center justify-center text-center">
            <span class="text-primary text-2xl font-semibold tracking-tighter"
              >Failed to get the data.</span
            >
          </div>

          <!-- Search results with no matches (must be before generic "No posts" check) -->
          <div v-else-if="isSearching && posts?.length === 0" class="flex flex-col gap-y-4">
            <span class="text-4xl font-semibold tracking-tighter sm:text-5xl"
              >No results found for
              <span class="font-bold italic">"{{ debouncedSearchInput }}"</span></span
            >

            <span class="text-base tracking-tight sm:text-lg"
              >Maybe try a different keyword, or explore other topics—we're sure you'll find
              something awesome!</span
            >
          </div>

          <!-- No posts at all (not searching) -->
          <div v-else-if="posts?.length === 0" class="flex flex-col items-start">
            <h2
              class="text-4xl font-bold tracking-tighter text-black sm:text-4xl xl:text-5xl dark:text-white"
            >
              No posts yet
            </h2>
            <p class="mt-4 text-base tracking-tight sm:text-lg">Please come back later</p>

            <nuxt-link
              to="/"
              class="mt-4 flex items-center gap-x-1 rounded-full bg-black p-4 font-semibold tracking-tight text-white dark:bg-white dark:text-black"
            >
              <IconChevronLeft class="h-4" />
              <span>Back to Home</span>
            </nuxt-link>
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
usePageMeta(null, { title: "News" });
defineOptions({
  name: "news",
});

const route = useRoute();
const router = useRouter();

const postCardClasses = ref(
  "@xl:first:col-span-2 @4xl:col-span-4 @4xl:first:col-span-6 @4xl:nth-2:col-span-6 @5xl:col-span-3 @[90rem]:col-span-3 @7xl:[&:nth-child(-n+3)]:col-span-4"
);

const postStore = usePostStore();
const { posts, pending, error, meta } = storeToRefs(postStore);

// Get initial values from URL query parameters
const initialPage = Number(route.query.page) || 1;
const initialSearchQuery = (route.query.q || "").toString();

// Initialize search input with URL query
const searchInput = ref(initialSearchQuery);
const debouncedSearchInput = refDebounced(searchInput, 400);

// Fetch initial data based on URL parameters
// Always force fetch to ensure data matches current URL state
if (initialSearchQuery) {
  await postStore.searchPosts(initialSearchQuery, { page: initialPage });
} else {
  await postStore.fetchPosts({ page: initialPage, force: true });
}

// Check if user is searching
const isSearching = computed(() => debouncedSearchInput.value.length > 0);

// Current page for pagination
const currentPage = ref(initialPage);

// Flag to prevent duplicate fetches when search triggers page reset
const isSearchTriggeredPageChange = ref(false);

// Watch for search input changes and search from backend
watch(debouncedSearchInput, async (newSearchTerm) => {
  if (newSearchTerm.trim()) {
    // Mark that search is triggering the page change to prevent duplicate fetch
    isSearchTriggeredPageChange.value = true;
    currentPage.value = 1;
    await postStore.searchPosts(newSearchTerm, { page: 1 });
    isSearchTriggeredPageChange.value = false;

    // Update URL with search query (without page param since it's page 1)
    router.push({
      query: { q: newSearchTerm },
    });
  } else {
    // Mark that search clear is triggering the page change
    isSearchTriggeredPageChange.value = true;
    currentPage.value = 1;
    await postStore.clearSearch();
    isSearchTriggeredPageChange.value = false;

    // Remove search query from URL
    router.push({
      query: {},
    });
  }
});

// Watch for page changes and fetch new data (only for user-initiated pagination)
watch(currentPage, async (newPage) => {
  // Skip if this page change was triggered by search (already handled above)
  if (isSearchTriggeredPageChange.value) {
    return;
  }

  if (newPage !== meta.value.current_page) {
    if (isSearching.value) {
      await postStore.searchPosts(debouncedSearchInput.value, { page: newPage });
    } else {
      await postStore.goToPage(newPage);
    }

    // Update URL query parameter
    const query = {};
    if (newPage > 1) query.page = newPage;
    if (isSearching.value) query.q = debouncedSearchInput.value;
    router.push({ query });

    // Scroll to top of the page
    window.scrollTo({ top: 0 });
  }
});

// Sync currentPage with meta when meta changes (e.g., after fetch)
watch(
  () => meta.value.current_page,
  (newPage) => {
    currentPage.value = newPage;
  }
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
