<template>
  <header
    class="flex h-(--navbar-height-mobile) items-center justify-center text-sm lg:h-(--navbar-height-desktop)"
    :class="{
      'sticky inset-x-0 top-0 z-50': ![].includes(route.name),
      'bg-background': isMenuOpen,
      'border-border/30 bg-background/95 supports-backdrop-filter:bg-background/90 border-b backdrop-blur-sm':
        !isMenuOpen,
    }"
  >
    <nav
      class="flex h-full items-center transition-all duration-300"
      :class="['news', 'docs', 'docs-slug'].includes(route.name) ? 'container-wider' : 'container'"
    >
      <!-- Docs mobile: Menu button replacing logo -->
      <button
        v-if="isDocsPage"
        class="text-primary flex items-center gap-x-1.5 rounded-lg text-sm font-medium tracking-tight lg:hidden"
        @click="toggleSidebar"
      >
        <Icon name="lucide:menu" class="size-4" />
        <span>Menu</span>
      </button>

      <nuxt-link
        to="/"
        aria-label="Home"
        @click="$scrollToTopIfCurrentPageIs('/')"
        v-ripple
        class="flex items-center gap-x-2"
        :class="{ 'hidden lg:flex': isDocsPage }"
      >
        <div
          class="bg-primary text-primary-foreground squircle flex aspect-square size-8 items-center justify-center rounded-lg"
        >
          <LogoMark class="size-4" />
        </div>

        <span class="text-primary text-base font-semibold tracking-tighter sm:text-lg">PM One</span>
      </nuxt-link>

      <div class="ml-auto flex h-full items-center gap-x-6">
        <!-- <HeaderNav class="hidden xl:absolute xl:left-1/2 xl:flex xl:-translate-x-1/2" /> -->

        <div class="flex h-full shrink-0 items-center gap-x-1.5 sm:gap-x-2">
          <ColorModeToggle />

          <Button
            to="/docs"
            variant="outline"
            size="sm"
            class="font-semibold tracking-tighter select-none active:scale-98 sm:text-base"
            v-ripple
          >
            <span>Docs</span>
          </Button>

          <Tippy v-if="['news-slug'].includes(route.name)">
            <button
              data-sidebar="trigger"
              data-slot="sidebar-trigger"
              class="text-primary hover:bg-muted flex size-8 items-center justify-center rounded-lg"
              @click="toggleSidebar"
            >
              <Icon
                v-if="open && !isMobile"
                name="hugeicons:sidebar-right-01"
                class="text-primary size-5"
              />
              <Icon v-else name="hugeicons:sidebar-right" class="text-primary size-5" />
            </button>
            <template #content>
              <span class="inline-flex items-center gap-x-1.5 tracking-tight">
                <span>Toggle Sidebar</span>
                <kbd class="keyboard-symbol">{{ metaSymbol }} B</kbd>
              </span>
            </template>
          </Tippy>

          <template v-if="isAuthenticated">
            <AuthDropdownMenu />
          </template>

          <template v-else>
            <Button
              to="/login"
              variant="outline"
              size="sm"
              class="font-semibold tracking-tighter select-none active:scale-98 sm:text-base"
              @click="$scrollToTopIfCurrentPageIs('login')"
              v-ripple
            >
              <span>Log in</span>
              <KbdGroup>
                <Kbd>L</Kbd>
              </KbdGroup>
            </Button>

            <Button
              to="/signup"
              size="sm"
              class="font-semibold tracking-tighter select-none active:scale-98 sm:text-base"
              @click="$scrollToTopIfCurrentPageIs('signup')"
              v-ripple
            >
              Sign up
            </Button>
          </template>
        </div>
      </div>
    </nav>
  </header>
</template>

<script setup>
const route = useRoute();
const router = useRouter();
const { metaSymbol } = useShortcuts();

const isMenuOpen = ref(false);

const { isAuthenticated } = useSanctumAuth();

defineShortcuts({
  l: {
    handler: () => {
      if (!isAuthenticated.value) {
        router.push("/login");
      }
    },
  },
});

import { useSidebar } from "@/components/ui/sidebar/utils";
const { toggleSidebar, open, isMobile } = useSidebar();

const isDocsPage = computed(() => route.name?.toString().startsWith("docs"));
</script>
