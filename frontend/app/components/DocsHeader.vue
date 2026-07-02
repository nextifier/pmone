<template>
  <header
    class="border-border/50 bg-background sticky inset-x-0 top-0 z-50 h-(--navbar-height-mobile) gap-x-1.5 border-b text-sm lg:h-(--navbar-height-desktop)"
  >
    <div class="flex h-full items-center justify-center gap-x-2 px-4">
      <Tippy>
        <button
          data-sidebar="trigger"
          data-slot="sidebar-trigger"
          class="text-foreground hover:bg-muted flex size-8 items-center justify-center rounded-lg"
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
            <span>Toggle Sidebar</span>
            <kbd class="keyboard-symbol">{{ metaSymbol }} B</kbd>
          </span>
        </template>
      </Tippy>

      <div class="flex grow items-center justify-end gap-x-1 sm:gap-x-2">
        <DocsSearch class="grow" />
        <Button
          v-if="isAuthenticated"
          to="/dashboard"
          variant="outline"
          size="sm"
          class="hidden text-base tracking-tighter sm:flex"
          v-ripple
        >
          <span>Dashboard</span>
        </Button>
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
            <span>Log in</span>
            <KbdGroup>
              <Kbd>L</Kbd>
            </KbdGroup>
          </Button>

          <Button
            to="/signup"
            size="sm"
            class="text-base tracking-tighter"
            @click="$scrollToTopIfCurrentPageIs('signup')"
            v-ripple
          >
            Sign up
          </Button>
        </template>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ColorModeToggle } from "@/components/ui/color-mode-toggle";
import { useSidebar } from "@/components/ui/sidebar/utils";

const { toggleSidebar, open, isMobile } = useSidebar();
const { metaSymbol } = useShortcuts();
const { isAuthenticated } = useSanctumAuth();
const router = useRouter();

defineShortcuts({
  l: {
    handler: () => {
      if (!isAuthenticated.value) {
        router.push("/login");
      }
    },
  },
});
</script>
