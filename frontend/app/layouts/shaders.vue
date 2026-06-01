<template>
  <SidebarProvider :style="{ '--sidebar-width': '18rem' }">
    <ShadersSidebar :current-name="currentName" />
    <SidebarInset>
      <ShadersHeader sidebar />
      <div class="min-h-screen-offset">
        <div class="container-wider">
          <slot />
        </div>
      </div>
    </SidebarInset>
  </SidebarProvider>
</template>

<script setup>
const route = useRoute();
const currentName = computed(() => {
  const s = route.params.slug;
  return Array.isArray(s) ? s.join("/") : s || "";
});
</script>
