<script setup lang="ts">
import { cn } from "@/lib/utils";
import { defaultDocument, useEventListener, useVModel } from "@vueuse/core";
import { TooltipProvider } from "reka-ui";
import type { HTMLAttributes, Ref } from "vue";
import { computed, ref } from "vue";
import {
  provideSidebarContext,
  SIDEBAR_COOKIE_MAX_AGE,
  SIDEBAR_COOKIE_NAME,
  SIDEBAR_KEYBOARD_SHORTCUT,
  SIDEBAR_WIDTH,
  SIDEBAR_WIDTH_ICON,
} from "./utils";

const props = withDefaults(
  defineProps<{
    defaultOpen?: boolean;
    open?: boolean;
    class?: HTMLAttributes["class"];
  }>(),
  {
    defaultOpen: !defaultDocument?.cookie.includes(`${SIDEBAR_COOKIE_NAME}=false`),
    open: undefined,
  }
);

const emits = defineEmits<{
  "update:open": [open: boolean];
}>();

// const isMobile = useMediaQuery("(max-width: 1024px)");
// const isMobile = useMediaQuery("(max-width: 1024px)", { ssrWidth: 1024 });

const isHydrated = ref(false);
const isMobile = ref(false); // Default to desktop (no layout shift)

// Initialize after hydration to prevent mismatch
onMounted(() => {
  // Now we can safely detect mobile
  isMobile.value = window.innerWidth <= 1024;
  isHydrated.value = true;

  // Setup reactive media query after hydration
  const mediaQuery = window.matchMedia("(max-width: 1024px)");
  isMobile.value = mediaQuery.matches;

  // Listen for changes
  const updateMobile = (e: MediaQueryListEvent) => {
    isMobile.value = e.matches;
  };

  mediaQuery.addEventListener("change", updateMobile);

  // Cleanup
  onUnmounted(() => {
    mediaQuery.removeEventListener("change", updateMobile);
  });
});

const openMobile = ref(false);

const open = useVModel(props, "open", emits, {
  defaultValue: props.defaultOpen ?? false,
  passive: (props.open === undefined) as false,
}) as Ref<boolean>;

function setOpen(value: boolean) {
  open.value = value; // emits('update:open', value)

  // This sets the cookie to keep the sidebar state.
  document.cookie = `${SIDEBAR_COOKIE_NAME}=${open.value}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}`;
}

function setOpenMobile(value: boolean) {
  openMobile.value = value;
}

// Helper to toggle the sidebar.
function toggleSidebar() {
  return isMobile.value ? setOpenMobile(!openMobile.value) : setOpen(!open.value);
}

useEventListener("keydown", (event: KeyboardEvent) => {
  if (event.key === SIDEBAR_KEYBOARD_SHORTCUT && (event.metaKey || event.ctrlKey)) {
    event.preventDefault();
    toggleSidebar();
  }
});

// We add a state so that we can do data-state="expanded" or "collapsed".
// This makes it easier to style the sidebar with Tailwind classes.
const state = computed(() => (open.value ? "expanded" : "collapsed"));

provideSidebarContext({
  state,
  open,
  setOpen,
  isMobile,
  openMobile,
  setOpenMobile,
  toggleSidebar,
  isHydrated,
});
</script>

<template>
  <TooltipProvider :delay-duration="0">
    <div
      data-slot="sidebar-wrapper"
      :style="{
        '--sidebar-width': SIDEBAR_WIDTH,
        '--sidebar-width-icon': SIDEBAR_WIDTH_ICON,
      }"
      :class="
        cn(
          'group/sidebar-wrapper has-data-[variant=inset]:bg-sidebar flex min-h-svh w-full',
          props.class
        )
      "
      v-bind="$attrs"
    >
      <slot />
    </div>
  </TooltipProvider>
</template>
