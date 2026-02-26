<template>
  <header
    class="border-border/50 bg-background/95 supports-backdrop-filter:bg-background/90 sticky inset-x-0 top-0 z-50 h-(--navbar-height-mobile) gap-x-1.5 border-b text-sm backdrop-blur-sm lg:h-(--navbar-height-desktop)"
  >
    <div class="container flex h-full items-center justify-center">
      <template v-if="hideSidebar">
        <div class="-ml-2 flex grow items-center gap-x-1 overflow-hidden">
          <BackButton :destination="backDestination" :force-destination="true">
            <template #default="{ goBack }">
              <button
                @click="goBack"
                class="text-primary/80 hover:text-primary hover:bg-muted flex size-8 shrink-0 items-center justify-center gap-x-1 rounded-lg text-sm tracking-tight transition active:scale-98"
              >
                <Icon name="hugeicons:arrow-left-02" class="size-5 shrink-0" />
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
                  class="truncate overflow-visible text-sm font-semibold tracking-tight decoration-dotted decoration-2 underline-offset-4 hover:underline"
                  >{{ headerProject.name }}</span
                >
              </NuxtLink>

              <template v-if="headerEvent">
                <Icon
                  name="hugeicons:arrow-right-01"
                  class="text-muted-foreground size-3.5 shrink-0"
                />
                <NuxtLink
                  :to="`/projects/${route.params.username}/events/${route.params.eventSlug}`"
                  class="truncate overflow-visible text-sm font-semibold tracking-tight decoration-dotted decoration-2 underline-offset-4 hover:underline"
                >
                  {{ headerEvent.title }}
                </NuxtLink>

                <template v-if="headerBrand">
                  <Icon
                    name="hugeicons:arrow-right-01"
                    class="text-muted-foreground size-3.5 shrink-0"
                  />
                  <NuxtLink
                    :to="`/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${route.params.brandSlug}`"
                    class="truncate overflow-visible text-sm font-semibold tracking-tight decoration-dotted decoration-2 underline-offset-4 hover:underline"
                  >
                    {{ headerBrand.brand?.name }}
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
const headerProject = useState("header-project", () => null);
const headerEvent = useState("header-event", () => null);
const headerBrand = useState("header-brand", () => null);

const backDestination = computed(() => {
  if (route.params.brandSlug) {
    return `/projects/${route.params.username}/events/${route.params.eventSlug}/brands`;
  }
  if (route.params.eventSlug) {
    return `/projects/${route.params.username}`;
  }
  return "/projects";
});
</script>
