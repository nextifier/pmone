<template>
  <Sidebar>
    <SidebarHeader>
      <NuxtLink
        to="/ui"
        class="hover:bg-muted flex items-center gap-x-2 rounded-lg p-2"
        @click="setOpenMobile(false)"
      >
        <div
          class="bg-sidebar-primary text-sidebar-primary-foreground squircle flex aspect-square size-10 items-center justify-center rounded-lg"
        >
          <LogoMark class="text-primary-foreground size-5" />
        </div>

        <div class="flex flex-col gap-y-1">
          <span class="line-clamp-1 text-base leading-none font-medium tracking-tight"
            >UI Library</span
          >
          <span class="text-muted-foreground line-clamp-1 text-sm leading-none tracking-tight">
            Components and patterns
          </span>
        </div>
      </NuxtLink>
    </SidebarHeader>

    <SidebarContent class="pb-10">
      <SidebarGroup v-for="group in sidebarNav" :key="group.label">
        <SidebarGroupLabel class="text-sm tracking-tight">{{ group.label }}</SidebarGroupLabel>
        <ul class="flex flex-col">
          <li v-for="item in group.items" :key="item.name">
            <NuxtLink
              :to="`/ui/${item.name}`"
              class="text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground block rounded-md px-2 py-1.5 text-base tracking-tight transition"
              :class="
                currentName === item.name &&
                'bg-sidebar-accent text-sidebar-accent-foreground font-medium'
              "
              @click="setOpenMobile(false)"
            >
              {{ item.title }}
            </NuxtLink>
          </li>
        </ul>
      </SidebarGroup>
    </SidebarContent>
    <SidebarRail />
  </Sidebar>
</template>

<script setup>
import { sidebarNav } from "@/components/ui-docs/sidebar-nav";
import { useSidebar } from "@/components/ui/sidebar/utils";

const { setOpenMobile } = useSidebar();

defineProps({
  currentName: {
    type: String,
    default: "",
  },
});
</script>
