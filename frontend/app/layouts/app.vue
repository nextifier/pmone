<template>
  <SidebarProvider variant="sidebar" :defaultOpen="defaultOpen" id="layout-app">
    <AppSidebar v-if="!isExcluded && !isProjectPage" class="select-none" />

    <SidebarInset
      :class="isExcluded ? 'contents' : 'mx-auto min-h-screen max-w-[1920px]'"
    >
      <AppHeader v-if="!isExcluded" :hide-sidebar="isProjectPage" />
      <div
        :class="isExcluded ? 'contents' : 'grow overflow-x-clip px-4'"
      >
        <slot />
      </div>
    </SidebarInset>
  </SidebarProvider>
</template>

<script setup>
const defaultOpen = useCookie("sidebar_state");
const route = useRoute();

const isExcluded = computed(() => ["posts-create", "posts-slug-edit"].includes(route.name));

const isProjectPage = computed(() => {
  const name = route.name?.toString() || "";
  return name === "projects-username" || name.startsWith("projects-username-");
});

// Block default "user" role from accessing pages beyond dashboard & settings
const { hasRole, hasAnyRole } = usePermission();
const isDefaultUser = computed(
  () => hasRole("user") && !hasAnyRole(["staff", "admin", "master", "exhibitor", "writer"]),
);

const allowedPrefixes = ["/dashboard", "/settings"];

watch(
  () => route.path,
  (path) => {
    if (isDefaultUser.value && !allowedPrefixes.some((p) => path === p || path.startsWith(p + "/"))) {
      navigateTo("/dashboard");
    }
  },
  { immediate: true },
);
</script>
