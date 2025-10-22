<template>
  <Sidebar v-bind="props">
    <SidebarHeader>
      <NuxtLink
        to="/dashboard"
        class="hover:bg-muted flex items-center gap-x-2 rounded-lg p-2 group-data-[state=collapsed]:p-0"
        @click="setOpenMobile(false)"
      >
        <div
          class="bg-sidebar-primary text-sidebar-primary-foreground squircle flex aspect-square size-10 items-center justify-center rounded-lg group-data-[state=collapsed]:size-8"
        >
          <LogoMark class="text-primary-foreground size-5 group-data-[state=expanded]:size-6" />
        </div>

        <div class="flex flex-col group-data-[state=collapsed]:hidden">
          <span class="line-clamp-1 text-base tracking-tight">PM One</span>
          <span class="line-clamp-1 text-xs">Enterprise</span>
        </div>
      </NuxtLink>
    </SidebarHeader>
    <SidebarContent>
      <AppSidebarNavMain />
      <AppSidebarNavProjects />
    </SidebarContent>
    <SidebarFooter>
      <AppSidebarNavUser />
    </SidebarFooter>
    <SidebarRail />
  </Sidebar>
</template>

<script setup>
import { useSidebar } from "@/components/ui/sidebar/utils";
const { setOpenMobile } = useSidebar();

const props = defineProps({
  collapsible: {
    type: String,
    default: "icon",
  },
});
</script>
