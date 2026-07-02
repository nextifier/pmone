<template>
  <Sidebar v-bind="props">
    <SidebarHeader>
      <NuxtLink
        to="/dashboard"
        class="hover:bg-muted flex items-center gap-x-2 rounded-lg p-2 group-data-[state=collapsed]:p-0"
        @click="setOpenMobile(false)"
      >
        <div
          class="bg-foreground text-background squircle flex aspect-square size-10 items-center justify-center rounded-lg group-data-[state=collapsed]:size-8"
        >
          <LogoMark class="text-background size-4 group-data-[state=expanded]:size-5" />
        </div>

        <div class="flex flex-col group-data-[state=collapsed]:hidden">
          <span class="line-clamp-1 text-base tracking-tight">PM One</span>
          <span class="line-clamp-1 text-xs">{{ portalLabel }}</span>
        </div>
      </NuxtLink>
    </SidebarHeader>
    <SidebarContent class="gap-y-1">
      <template v-if="isAiPage">
        <AppSidebarAiConversations />
      </template>
      <template v-else>
        <AppSidebarNavMain />
        <AppSidebarNavProjects v-if="!isExhibitor && !isWriter" />
      </template>
    </SidebarContent>
    <SidebarFooter>
      <AppSidebarAiUsage v-if="isAiPage" />
      <AppSidebarNavUser />
    </SidebarFooter>
    <SidebarRail />
  </Sidebar>
</template>

<script setup>
import { useSidebar } from "@/components/ui/sidebar/utils";
const { setOpenMobile } = useSidebar();
const route = useRoute();

useSidebarAutoScroll();

const { hasRole, isStaffOrAbove } = usePermission();
const isAiPage = computed(() => String(route.name) === "ai");
const isExhibitor = computed(() => hasRole("exhibitor") && !isStaffOrAbove.value);
const isWriter = computed(() => hasRole("writer") && !isStaffOrAbove.value);

const portalLabel = computed(() => {
  if (isStaffOrAbove.value) return "Staff Dashboard";
  if (isWriter.value) return "Writer Dashboard";
  if (isExhibitor.value) return "Exhibitor Dashboard";
  return "User Dashboard";
});

const props = defineProps({
  collapsible: {
    type: String,
    default: "icon",
  },
});
</script>
