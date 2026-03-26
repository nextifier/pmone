<template>
  <header
    class="border-border/50 bg-background sticky inset-x-0 top-0 z-50 h-(--navbar-height-mobile) gap-x-1.5 border-b text-sm lg:h-(--navbar-height-desktop)"
  >
    <div class="flex h-full items-center justify-center px-4">
      <template v-if="hideSidebar">
        <HeaderBreadcrumb />
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

      <div class="ml-auto flex h-full shrink-0 items-center gap-x-1 sm:gap-x-2">
        <Button
          to="/docs"
          variant="outline"
          size="sm"
          class="mr-1 text-base tracking-tighter"
          v-ripple
        >
          <span>Docs</span>
        </Button>

        <LanguageSwitcher v-if="isExhibitor" />

        <KeyboardShortcutsDialog class="hidden sm:flex" />

        <ColorModeToggle />

        <template v-if="isAuthenticated">
          <NotificationBell />
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

const isExhibitor = computed(() => hasRole("exhibitor") && !isStaffOrAbove.value);
</script>
