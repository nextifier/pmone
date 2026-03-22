<template>
  <header
    class="border-border/50 bg-background sticky inset-x-0 top-0 z-50 h-(--navbar-height-mobile) gap-x-1.5 border-b text-sm lg:h-(--navbar-height-desktop)"
  >
    <div class="flex h-full items-center justify-center px-4">
      <template v-if="hideSidebar">
        <div class="-ml-2 flex grow items-center gap-x-2 overflow-hidden sm:ml-0 sm:gap-x-2.5">
          <BackButton :destination="backDestination" :force-destination="forceBackDestination">
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
          </BackButton>

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
                      v-if="headerBrand.brand?.brand_logo"
                      :model="{
                        name: headerBrand.brand.name,
                        profile_image: headerBrand.brand.brand_logo,
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
      <template v-else>
        <Tippy>
          <button
            data-sidebar="trigger"
            data-slot="sidebar-trigger"
            class="text-primary hover:bg-muted flex size-8 items-center justify-center rounded-lg"
            @click="toggleSidebar"
          >
            <Icon
              v-if="open && !isMobile"
              name="hugeicons:sidebar-left-01"
              class="text-primary size-5"
            />
            <Icon v-else name="hugeicons:sidebar-left" class="text-primary size-5" />
          </button>
          <template #content>
            <span class="inline-flex items-center gap-x-1.5 tracking-tight">
              <span>{{ $t("header.toggleSidebar") }}</span>
              <kbd class="keyboard-symbol">{{ metaSymbol }} B</kbd>
            </span>
          </template>
        </Tippy>
      </template>

      <div class="ml-auto flex h-full shrink-0 items-center gap-x-6">
        <div class="flex h-full shrink-0 items-center gap-x-2">
          <LanguageSwitcher v-if="isExhibitor" />

          <DialogResponsive v-model:open="shortcutsDialogOpen" dialogMaxWidth="500px">
            <template #trigger>
              <Tippy>
                <button
                  class="text-primary hover:bg-muted flex size-8 items-center justify-center rounded-lg"
                  @click="shortcutsDialogOpen = true"
                >
                  <Icon name="hugeicons:keyboard" class="size-5" />
                </button>
                <template #content>
                  <span class="inline-flex items-center gap-x-1.5 tracking-tight">
                    <span>Keyboard Shortcuts</span>
                    <kbd class="keyboard-symbol">?</kbd>
                  </span>
                </template>
              </Tippy>
            </template>

            <template #default>
              <div class="px-4 pb-10 md:px-6 md:py-5">
                <h2 class="text-primary pr-8 text-lg font-semibold tracking-tight">
                  Keyboard Shortcuts
                </h2>

                <div class="mt-5 space-y-5">
                  <div>
                    <h3 class="text-muted-foreground text-sm font-medium tracking-tight uppercase">
                      General
                    </h3>
                    <div class="divide-border mt-2 divide-y">
                      <div class="flex items-center justify-between py-2">
                        <span class="text-sm tracking-tight">Toggle Light / Dark Mode</span>
                        <KbdGroup class="inline-flex">
                          <Kbd>D</Kbd>
                        </KbdGroup>
                      </div>
                      <div class="flex items-center justify-between py-2">
                        <span class="text-sm tracking-tight">Go Back</span>
                        <KbdGroup class="inline-flex">
                          <Kbd>B</Kbd>
                        </KbdGroup>
                      </div>
                      <div class="flex items-center justify-between py-2">
                        <span class="text-sm tracking-tight">New Item</span>
                        <KbdGroup class="inline-flex">
                          <Kbd>N</Kbd>
                        </KbdGroup>
                      </div>
                      <div class="flex items-center justify-between py-2">
                        <span class="text-sm tracking-tight">Toggle Sidebar</span>
                        <KbdGroup class="inline-flex">
                          <Kbd>{{ metaSymbol }}</Kbd>
                          <Kbd>B</Kbd>
                        </KbdGroup>
                      </div>
                      <div class="flex items-center justify-between py-2">
                        <span class="text-sm tracking-tight">Search</span>
                        <KbdGroup class="inline-flex">
                          <Kbd>{{ metaSymbol }}</Kbd>
                          <Kbd>K</Kbd>
                        </KbdGroup>
                      </div>
                      <div class="flex items-center justify-between py-2">
                        <span class="text-sm tracking-tight">Save</span>
                        <KbdGroup class="inline-flex">
                          <Kbd>{{ metaSymbol }}</Kbd>
                          <Kbd>S</Kbd>
                        </KbdGroup>
                      </div>
                    </div>
                  </div>

                  <div>
                    <h3 class="text-muted-foreground text-sm font-medium tracking-tight uppercase">
                      Navigation
                    </h3>
                    <div class="divide-border mt-2 divide-y">
                      <div class="flex items-center justify-between py-2">
                        <span class="text-sm tracking-tight">Close dialog, dropdown, drawer</span>
                        <KbdGroup class="inline-flex">
                          <Kbd>Esc</Kbd>
                        </KbdGroup>
                      </div>
                      <div class="flex items-center justify-between py-2">
                        <span class="text-sm tracking-tight">Navigate focusable elements</span>
                        <KbdGroup class="inline-flex">
                          <Kbd>Tab</Kbd>
                        </KbdGroup>
                      </div>
                      <div class="flex items-center justify-between py-2">
                        <span class="text-sm tracking-tight">Confirm / Select</span>
                        <KbdGroup class="inline-flex">
                          <Kbd>Enter</Kbd>
                        </KbdGroup>
                      </div>
                      <div class="flex items-center justify-between py-2">
                        <span class="text-sm tracking-tight">Toggle / Select</span>
                        <KbdGroup class="inline-flex">
                          <Kbd>Space</Kbd>
                        </KbdGroup>
                      </div>
                      <div class="flex items-center justify-between py-2">
                        <span class="text-sm tracking-tight">Navigate list items</span>
                        <KbdGroup class="inline-flex">
                          <Kbd>↑</Kbd>
                          <Kbd>↓</Kbd>
                        </KbdGroup>
                      </div>
                      <div class="flex items-center justify-between py-2">
                        <span class="text-sm tracking-tight">Navigate tabs</span>
                        <KbdGroup class="inline-flex">
                          <Kbd>←</Kbd>
                          <Kbd>→</Kbd>
                        </KbdGroup>
                      </div>
                    </div>
                  </div>

                  <div>
                    <h3 class="text-muted-foreground text-sm font-medium tracking-tight uppercase">
                      Gestures
                    </h3>
                    <div class="divide-border mt-2 divide-y">
                      <div class="flex items-center justify-between py-2">
                        <span class="text-sm tracking-tight">Close drawer</span>
                        <span class="text-muted-foreground text-sm font-medium tracking-tight"
                          >Swipe Down</span
                        >
                      </div>
                      <div class="flex items-center justify-between py-2">
                        <span class="text-sm tracking-tight">Close sidebar</span>
                        <span class="text-muted-foreground text-sm font-medium tracking-tight"
                          >Swipe Left</span
                        >
                      </div>
                      <div class="flex items-center justify-between py-2">
                        <span class="text-sm tracking-tight">Navigate between tabs</span>
                        <span class="text-muted-foreground text-sm font-medium tracking-tight"
                          >Swipe Left / Right</span
                        >
                      </div>
                      <div class="flex items-center justify-between py-2">
                        <span class="text-sm tracking-tight">Reorder list items</span>
                        <span class="text-muted-foreground text-sm font-medium tracking-tight"
                          >Drag Handle</span
                        >
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </DialogResponsive>

          <Tippy>
            <ColorModeToggle />
            <template #content>
              <span class="inline-flex items-center gap-x-1.5 tracking-tight">
                <span>{{ $t("header.lightDarkMode") }}</span>
                <kbd class="keyboard-symbol">{{ metaSymbol }} D</kbd>
              </span>
            </template>
          </Tippy>

          <template v-if="isAuthenticated">
            <Tippy>
              <NotificationBell />
              <template #content>
                <span class="inline-flex items-center gap-x-1.5 tracking-tight">
                  <span>Notifications</span>
                </span>
              </template>
            </Tippy>
            <AuthDropdownMenu />
          </template>

          <template v-else>
            <nuxt-link
              to="/login"
              class="hover:bg-muted border-border text-primary flex items-center justify-center rounded-lg border px-2.5 py-1.5 font-semibold tracking-tight transition select-none active:scale-98 sm:px-2.5 sm:py-1.5"
              @click="$scrollToTopIfCurrentPageIs('login')"
              v-ripple
            >
              <span>{{ $t("header.login") }}</span>
            </nuxt-link>

            <nuxt-link
              to="/signup"
              class="hover:bg-primary/80 bg-primary text-primary-foreground flex items-center justify-center rounded-lg px-2.5 py-1.5 font-semibold tracking-tight transition select-none active:scale-98 sm:px-2.5 sm:py-1.5"
              @click="$scrollToTopIfCurrentPageIs('signup')"
              v-ripple
              >{{ $t("header.signup") }}</nuxt-link
            >
          </template>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { useSidebar } from "@/components/ui/sidebar/utils";

defineProps({
  hideSidebar: {
    type: Boolean,
    default: false,
  },
});

const { toggleSidebar, open, isMobile } = useSidebar();
const { metaSymbol } = useShortcuts();
const { isAuthenticated } = useSanctumAuth();
const { hasRole, isStaffOrAbove } = usePermission();
const route = useRoute();

const isExhibitor = computed(() => hasRole("exhibitor") && !isStaffOrAbove.value);

const shortcutsDialogOpen = ref(false);
defineShortcuts({
  "?": {
    handler: () => {
      shortcutsDialogOpen.value = !shortcutsDialogOpen.value;
    },
  },
});
const headerProject = useState("header-project", () => null);
const headerEvent = useState("header-event", () => null);
const headerBrand = useState("header-brand", () => null);

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
