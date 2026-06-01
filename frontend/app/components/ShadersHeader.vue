<template>
  <header
    class="border-border/50 bg-background sticky inset-x-0 top-0 z-50 h-(--navbar-height-mobile) border-b lg:h-(--navbar-height-desktop)"
  >
    <div
      :class="[
        'flex h-full items-center gap-x-2',
        sidebar ? 'px-4' : 'container-wider',
      ]"
    >
      <ShadersSidebarTrigger v-if="sidebar" />

      <NuxtLink v-else to="/shaders" class="flex items-center gap-x-2">
        <div
          class="bg-sidebar-primary text-sidebar-primary-foreground squircle flex aspect-square size-8 items-center justify-center rounded-lg"
        >
          <Icon name="hugeicons:pathfinder-unite" class="size-4" />
        </div>
        <span class="text-base font-semibold tracking-tight">Shaders</span>
      </NuxtLink>

      <nav class="hidden items-center gap-x-1 sm:flex">
        <Button
          v-for="link in links"
          :key="link.to"
          :to="link.to"
          variant="ghost"
          size="sm"
          class="text-muted-foreground data-[active=true]:text-foreground"
        >
          {{ link.label }}
        </Button>
      </nav>

      <div class="ml-auto flex grow items-center justify-end gap-x-1 sm:gap-x-2">
        <ShadersSearch class="grow" />
        <ColorModeToggle />
      </div>
    </div>
  </header>
</template>

<script setup>
import { ColorModeToggle } from "@/components/ui/color-mode-toggle";

defineProps({
  sidebar: {
    type: Boolean,
    default: false,
  },
});

const links = [
  { label: "Gallery", to: "/shaders" },
  { label: "Docs", to: "/shaders/docs" },
  { label: "Editor", to: "/shaders/editor" },
];
</script>
