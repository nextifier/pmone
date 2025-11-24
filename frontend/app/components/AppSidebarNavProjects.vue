<template>
  <SidebarGroup
    v-if="projects?.length && isStaffOrAbove"
  >
    <Collapsible as-child :default-open="true" class="group/collapsible">
      <CollapsibleTrigger as-child>
        <SidebarGroupLabel class="text-muted-foreground hover:bg-muted mb-1 tracking-tight">
          <span>Projects</span>
          <Icon
            name="lucide:chevron-right"
            class="ml-auto size-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
          />
        </SidebarGroupLabel>
      </CollapsibleTrigger>
      <CollapsibleContent>
        <SidebarMenu class="gap-y-1.5">
          <div v-for="project in projects" :key="project?.id" class="tracking-tight">
            <NuxtLink
              :to="`/projects/${project?.username}`"
              @click="setOpenMobile(false)"
              activeClass="*:bg-muted"
            >
              <SidebarMenuButton :tooltip="project?.name">
                <Avatar
                  :model="project"
                  class="size-6 shrink-0 group-data-[state=collapsed]:size-4.5 group-data-[state=collapsed]:scale-150"
                />
                <span class="line-clamp-1 text-sm tracking-tight">{{ project?.name }}</span>
              </SidebarMenuButton>
            </NuxtLink>
          </div>
        </SidebarMenu>
      </CollapsibleContent>
    </Collapsible>
  </SidebarGroup>
</template>

<script setup>
import { useSidebar } from "@/components/ui/sidebar/utils";
import { storeToRefs } from "pinia";

const { setOpenMobile } = useSidebar();
const { user } = useSanctumAuth();
const { isStaffOrAbove } = usePermission();

const projectsStore = useProjectsStore();
const { projects } = storeToRefs(projectsStore);

// Load projects on mount (only if not already loaded)
onMounted(() => {
  projectsStore.fetchProjects();
});
</script>
