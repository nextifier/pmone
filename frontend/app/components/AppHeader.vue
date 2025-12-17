<template>
  <header
    class="border-border/50 bg-background/95 supports-backdrop-filter:bg-background/90 sticky inset-x-0 top-0 z-50 flex h-(--navbar-height-mobile) items-center justify-center border-b px-4 text-sm backdrop-blur-sm lg:h-(--navbar-height-desktop)"
  >
    <Tippy v-if="!['posts-create', 'posts-slug-edit'].includes(route.name)">
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
          <span>Toggle Sidebar</span>
          <kbd class="keyboard-symbol">{{ metaSymbol }} B</kbd>
        </span>
      </template>
    </Tippy>

    <div class="ml-auto flex h-full items-center gap-x-6">
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
  </header>
</template>

<script setup>
import { useSidebar } from "@/components/ui/sidebar/utils";
const { toggleSidebar, open, isMobile } = useSidebar();
const { metaSymbol } = useShortcuts();
const { isAuthenticated } = useSanctumAuth();
const route = useRoute();
</script>
