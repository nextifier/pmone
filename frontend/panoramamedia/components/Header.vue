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
    <nav class="container flex h-full items-center">
      <nuxt-link
        to="/"
        aria-label="Home"
        @click="$scrollToTopIfCurrentPageIs('/')"
        v-ripple
      >
        <Logo class="text-primary h-9" />
      </nuxt-link>

      <div class="ml-auto flex h-full items-center gap-x-6">
        <HeaderNav
          class="hidden xl:absolute xl:left-1/2 xl:flex xl:-translate-x-1/2"
        />

        <div class="flex h-full shrink-0 items-center gap-x-2">
          <button
            @click="openInquiryDialog"
            class="hover:bg-primary/80 bg-primary text-primary-foreground hidden items-center justify-center gap-x-1.5 rounded-lg px-3 py-2 font-semibold tracking-tight transition select-none active:scale-95 sm:flex"
          >
            Let's Talk
          </button>

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
              <Icon
                name="lucide:panel-left"
                class="text-primary size-4 rotate-180"
              />
            </button>
            <template #content>
              <span class="inline-flex items-center gap-x-1.5 tracking-tight">
                <span>Toggle Sidebar</span>
                <kbd class="keyboard-symbol">{{ metaSymbol }} B</kbd>
              </span>
            </template>
          </Tippy>

          <Tippy v-else>
            <HeaderMenu v-model:open="isMenuOpen" />
            <template #content>
              <span class="inline-flex items-center gap-x-1.5 tracking-tight">
                <span>Open Menu</span>
                <kbd class="keyboard-symbol">{{ metaSymbol }} M</kbd>
              </span>
            </template>
          </Tippy>

          <button
            v-if="['winner'].includes(route.name)"
            @click="toggleFullScreen"
            type="button"
            v-tippy="'Toggle Fullscreen'"
            aria-label="Toggle Fullscreen"
            class="text-primary hover:bg-muted flex size-8 items-center justify-center rounded-lg"
          >
            <Icon name="lucide:fullscreen" class="text-primary size-4" />
          </button>
        </div>
      </div>
    </nav>
  </header>
</template>

<script setup>
import { useSidebar } from "@/components/ui/sidebar/utils";
const { toggleSidebar } = useSidebar();

const uiStore = useUiStore();
const openInquiryDialog = () => {
  uiStore.openInquiryDialog();
};

const route = useRoute();
const { metaSymbol } = useShortcuts();

const isMenuOpen = ref(false);

function toggleFullScreen() {
  const elem = document.documentElement; // Target the entire document

  if (!document.fullscreenElement) {
    // Enter fullscreen mode
    if (elem.requestFullscreen) {
      elem.requestFullscreen();
    }
  } else {
    // Exit fullscreen mode
    if (document.exitFullscreen) {
      document.exitFullscreen();
    }
  }
}
</script>
