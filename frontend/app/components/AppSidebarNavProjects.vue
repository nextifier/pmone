<template>
  <SidebarGroup
    v-if="
      projects?.length && user?.roles?.some((role) => ['master', 'admin', 'staff'].includes(role))
    "
  >
    <SidebarGroupLabel class="text-muted-foreground tracking-tight">Projects</SidebarGroupLabel>
    <SidebarMenu>
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
