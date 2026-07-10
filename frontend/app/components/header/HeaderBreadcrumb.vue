<template>
  <div class="-ml-2 flex grow items-center gap-x-2 overflow-hidden sm:ml-0 sm:gap-x-2.5">
    <ButtonBack :destination="backDestination" :force-destination="forceBackDestination">
      <template #default="{ goBack }">
        <button
          @click="goBack"
          class="text-primary/80 hover:text-primary sm:bg-card bg-muted border-border flex aspect-square h-9 shrink-0 items-center justify-center gap-x-1 rounded-full px-1 text-sm tracking-tight transition active:scale-98 sm:aspect-auto sm:h-8 sm:rounded-lg sm:border"
        >
          <Icon name="hugeicons:arrow-left-02" class="size-5 shrink-0" />
          <KbdGroup>
            <Kbd>B</Kbd>
          </KbdGroup>
        </button>
      </template>
    </ButtonBack>

    <ClientOnly>
      <div
        v-if="headerProject"
        class="scroll-fade-x no-scrollbar flex shrink grow items-center gap-x-1 overflow-auto"
      >
        <NuxtLink
          :to="`/projects/${route.params.username}`"
          class="flex items-center gap-x-1.5"
        >
          <Avatar :model="headerProject" class="size-7" rounded="rounded-sm" />
          <span
            class="truncate overflow-visible text-sm font-medium tracking-tight decoration-dotted decoration-2 underline-offset-4 hover:underline"
            >{{ headerProject.name }}</span
          >
        </NuxtLink>
        <HeaderProjectSwitcher />

        <template v-if="headerEvent">
          <Icon
            name="hugeicons:arrow-right-01"
            class="text-muted-foreground size-3.5 shrink-0"
          />
          <NuxtLink
            :to="`/projects/${route.params.username}/events/${route.params.eventSlug}`"
            class="group flex items-center gap-x-1.5 truncate overflow-visible"
          >
            <div
              v-if="headerEvent.poster_image?.sm"
              class="bg-muted outline-inside hidden aspect-4/5 w-7 overflow-hidden rounded-sm sm:block"
            >
              <img
                :src="headerEvent.poster_image.sm"
                :alt="headerEvent.title"
                class="size-full object-cover"
              />
            </div>
            <h3
              class="text-sm font-medium tracking-tight decoration-dotted decoration-2 underline-offset-4 group-hover:underline"
            >
              {{ headerEvent.title }}
            </h3>
          </NuxtLink>
          <HeaderEventSwitcher />

          <template v-if="headerBrand">
            <Icon
              name="hugeicons:arrow-right-01"
              class="text-muted-foreground size-3.5 shrink-0"
            />
            <NuxtLink
              :to="`/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${route.params.brandSlug}`"
              class="flex items-center gap-x-1.5"
            >
              <Avatar
                v-if="headerBrand.brand?.profile_image"
                :model="{
                  name: headerBrand.brand.name,
                  profile_image: headerBrand.brand.profile_image,
                }"
                class="size-7"
                rounded="rounded-sm"
              />
              <span
                class="truncate overflow-visible text-sm font-medium tracking-tight decoration-dotted decoration-2 underline-offset-4 hover:underline"
                >{{ headerBrand.brand?.name }}</span
              >
            </NuxtLink>
          </template>
        </template>
      </div>
    </ClientOnly>
  </div>
</template>

<script setup>
const route = useRoute();

const headerProject = useState("header-project", () => null);
const headerEvent = useState("header-event", () => null);
const headerBrand = useState("header-brand", () => null);

// Warm the shared navigation cache on header mount so both switchers
// (HeaderProjectSwitcher/HeaderEventSwitcher) show data instantly on open
// instead of flashing the empty state during the first deferred fetch.
const { fetchNavigation } = useHeaderNavigation();
onMounted(() => fetchNavigation());

const backDestination = computed(() => {
  const basePath = `/projects/${route.params.username}`;

  if (route.params.eventSlug) {
    const eventPath = `${basePath}/events/${route.params.eventSlug}`;

    // Brand detail → brands list
    if (route.params.brandSlug) {
      return `${eventPath}/brands`;
    }

    // Event overview (exact) → project overview
    if (route.path === eventPath || route.path === `${eventPath}/`) {
      return basePath;
    }

    // Product categories → products page
    if (route.path === `${eventPath}/product-categories`) {
      return `${eventPath}/operational/products`;
    }

    // Operational order detail → orders list
    if (route.path.match(/\/operational\/orders\/[^/]+$/)) {
      return `${eventPath}/operational/orders`;
    }

    // Any event sub-page (content, brands, orders, etc.) → event overview
    return eventPath;
  }

  if (route.params.username) {
    // Project overview → dashboard
    if (route.path === basePath || route.path === `${basePath}/`) {
      return "/dashboard";
    }

    // Project settings sub-pages → settings
    if (route.path.startsWith(`${basePath}/settings/`)) {
      return `${basePath}/settings`;
    }

    // Any project sub-page → project overview
    return basePath;
  }

  return "/dashboard";
});

// Di halaman project overview, cek history sebelum back
// Jika halaman sebelumnya adalah turunan projects/*, force ke /dashboard
// Jika halaman sebelumnya bukan projects/*, gunakan router.back()
const forceBackDestination = computed(() => {
  if (!route.params.username || route.params.eventSlug) return true;
  const basePath = `/projects/${route.params.username}`;
  if (route.path !== basePath && route.path !== `${basePath}/`) return true;

  // Cek halaman sebelumnya via history state
  const previousPath = window?.history?.state?.back;
  if (previousPath && typeof previousPath === "string" && !previousPath.startsWith("/projects/")) {
    return false; // Halaman sebelumnya bukan projects/*, gunakan router.back()
  }
  return true; // Halaman sebelumnya adalah projects/* atau tidak ada history, force ke /dashboard
});
</script>
