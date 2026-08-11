<template>
  <header
    class="border-border/50 bg-background sticky inset-x-0 top-0 z-50 h-(--navbar-height-mobile) gap-x-1.5 border-b text-sm lg:h-(--navbar-height-desktop)"
  >
    <div class="flex h-full items-center justify-center px-4">
      <!-- Project pages show the trigger *and* the breadcrumb: the sidebar is
           rendered there too now (as an icon rail), so hiding its toggle would
           leave the rail with no way back to full width. -->
      <div class="flex min-w-0 shrink items-center gap-x-1.5">
        <Tippy>
          <button
            data-sidebar="trigger"
            data-slot="sidebar-trigger"
            class="text-foreground hover:bg-muted flex size-8 shrink-0 items-center justify-center rounded-lg"
            @click="toggleSidebar"
          >
            <ClientOnly>
              <Icon
                v-if="open && !isMobile"
                name="hugeicons:sidebar-left-01"
                class="text-foreground size-5"
              />
              <Icon v-else name="hugeicons:sidebar-left" class="text-foreground size-5" />
              <template #fallback>
                <span class="size-5" aria-hidden="true" />
              </template>
            </ClientOnly>
          </button>
          <template #content>
            <span class="inline-flex items-center gap-x-1.5 tracking-tight">
              <span>{{ $t("header.toggleSidebar") }}</span>
              <kbd class="keyboard-symbol">{{ metaSymbol }} B</kbd>
            </span>
          </template>
        </Tippy>

        <HeaderBreadcrumb v-if="showBreadcrumb" />
      </div>

      <div class="ml-auto flex h-full shrink-0 items-center gap-x-1 sm:gap-x-2">
        <KeyboardShortcutsDialog class="hidden sm:flex" />

        <AppearancePickerButton />

        <ColorModeToggle />

        <template v-if="isAuthenticated">
          <Notifications />
          <AuthDropdownMenu />
        </template>

        <template v-else>
          <Button
            to="/login"
            variant="outline"
            size="sm"
            class="text-base tracking-tighter"
            @click="$scrollToTopIfCurrentPageIs('login')"
            v-ripple
          >
            {{ $t("header.login") }}
          </Button>

          <Button
            to="/signup"
            size="sm"
            class="text-base tracking-tighter"
            @click="$scrollToTopIfCurrentPageIs('signup')"
            v-ripple
          >
            {{ $t("header.signup") }}
          </Button>
        </template>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ColorModeToggle } from "@/components/ui/color-mode-toggle";
import { useSidebar } from "@/components/ui/sidebar/utils";

defineProps({
  showBreadcrumb: {
    type: Boolean,
    default: false,
  },
});

const { toggleSidebar, open, isMobile } = useSidebar();
const { metaSymbol } = useShortcuts();
const { isAuthenticated } = useSanctumAuth();
</script>
