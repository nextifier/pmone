<template>
  <SidebarGroup
    v-if="
      projects?.length && user?.roles?.some((role) => ['master', 'admin', 'staff'].includes(role))
    "
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
              :to="`/projects/${project?.username}/edit`"
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
const { setOpenMobile } = useSidebar();
const { user } = useSanctumAuth();

const sanctumFetch = useSanctumClient();
const projects = ref([]);

// Fetch projects
const fetchProjects = async () => {
  try {
    const params = new URLSearchParams();
    params.append("client_only", "true");
    params.append("sort", "order_column");
    const response = await sanctumFetch(`/api/projects?${params.toString()}`);
    projects.value = response.data || [];
  } catch (err) {
    console.error("Failed to fetch projects:", err);
    projects.value = [];
  }
};

// Load projects on mount
onMounted(() => {
  fetchProjects();
});
</script>
