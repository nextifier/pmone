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
      :class="['news'].includes(route.name) ? 'container-wider' : 'container'"
    >
      <nuxt-link to="/" aria-label="Home" @click="$scrollToTopIfCurrentPageIs('/')" v-ripple>
        <Logo class="text-primary h-6" />
      </nuxt-link>

      <div class="ml-auto flex h-full items-center gap-x-6">
        <!-- <HeaderNav
          class="hidden xl:absolute xl:left-1/2 xl:flex xl:-translate-x-1/2"
        /> -->

        <div class="flex h-full shrink-0 items-center gap-x-2">
          <Tippy>
            <ColorModeToggle />
            <template #content>
              <span class="inline-flex items-center gap-x-1.5 tracking-tight">
                <span>Light / Dark Mode</span>
                <kbd class="keyboard-symbol">{{ metaSymbol }} D</kbd>
              </span>
            </template>
          </Tippy>

          <Tippy v-if="['news-slug'].includes(route.name)">
            <button
              data-sidebar="trigger"
              data-slot="sidebar-trigger"
              class="text-primary hover:bg-muted flex size-8 items-center justify-center rounded-lg"
              @click="toggleSidebar"
            >
              <Icon name="hugeicons:sidebar-right-01" class="text-primary size-5" />
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
            <nuxt-link
              to="/login"
              class="hover:bg-muted border-border text-primary flex items-center justify-center rounded-lg border px-2.5 py-1.5 font-semibold tracking-tight transition select-none active:scale-98 sm:px-2.5 sm:py-1.5"
              @click="$scrollToTopIfCurrentPageIs('login')"
              v-ripple
            >
              <span>Log in</span>
            </nuxt-link>

            <nuxt-link
              to="/signup"
              class="hover:bg-primary/80 bg-primary text-primary-foreground flex items-center justify-center rounded-lg px-2.5 py-1.5 font-semibold tracking-tight transition select-none active:scale-98 sm:px-2.5 sm:py-1.5"
              @click="$scrollToTopIfCurrentPageIs('signup')"
              v-ripple
              >Sign up</nuxt-link
            >
          </template>
        </div>
      </div>
    </nav>
  </header>
</template>

<script setup>
const uiStore = useUiStore();
const openInquiryDialog = () => {
  uiStore.openInquiryDialog();
};

const route = useRoute();
const { metaSymbol } = useShortcuts();

const isMenuOpen = ref(false);

const { isAuthenticated } = useSanctumAuth();

import { useSidebar } from "@/components/ui/sidebar/utils";
const { toggleSidebar } = useSidebar();
</script>
