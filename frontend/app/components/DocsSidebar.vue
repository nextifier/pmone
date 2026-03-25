<template>
  <Sidebar>
    <SidebarHeader>
      <NuxtLink
        to="/"
        class="hover:bg-muted flex items-center gap-x-2 rounded-lg p-2"
        @click="setOpenMobile(false)"
      >
        <div
          class="bg-sidebar-primary text-sidebar-primary-foreground squircle flex aspect-square size-10 items-center justify-center rounded-lg"
        >
          <LogoMark class="text-primary-foreground size-5" />
        </div>

        <div class="flex flex-col">
          <span class="line-clamp-1 text-base tracking-tight">PM One</span>
          <span class="line-clamp-1 text-xs tracking-tight">Documentation</span>
        </div>
      </NuxtLink>
    </SidebarHeader>

    <SidebarContent class="pb-10">
      <!-- Loading skeleton -->
      <template v-if="pending">
        <SidebarGroup v-for="i in 3" :key="i">
          <Skeleton class="mb-1 h-3.5 w-20" />
          <SidebarMenu class="gap-0">
            <SidebarMenuItem v-for="j in i === 1 ? 3 : 4" :key="j">
              <Skeleton class="h-7 w-full rounded-md" />
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarGroup>
      </template>

      <!-- Docs navigation -->
      <template v-else>
        <SidebarGroup v-for="group in groupedDocs" :key="group.label">
          <SidebarGroupLabel class="text-sm tracking-tight">{{ group.label }}</SidebarGroupLabel>
          <ul class="flex flex-col">
            <li v-for="doc in group.docs" :key="doc.path">
              <NuxtLink
                :to="doc.path"
                class="text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground block rounded-md px-2 py-1.5 text-base tracking-tight transition"
                :class="
                  currentSlug === doc.path &&
                  'bg-sidebar-accent text-sidebar-accent-foreground font-medium'
                "
                @click="setOpenMobile(false)"
              >
                {{ doc.title }}
              </NuxtLink>
            </li>
          </ul>
        </SidebarGroup>
      </template>
    </SidebarContent>
    <SidebarRail />
  </Sidebar>
</template>

<script setup>
import { useSidebar } from "@/components/ui/sidebar/utils";

const { setOpenMobile } = useSidebar();

const props = defineProps({
  groupedDocs: {
    type: Array,
    default: () => [],
  },
  currentSlug: {
    type: String,
    default: "",
  },
  pending: {
    type: Boolean,
    default: false,
  },
});
</script>
