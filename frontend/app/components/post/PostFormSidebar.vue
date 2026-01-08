<template>
  <Sidebar v-bind="props">
    <SidebarContent>
      <SidebarGroup>
        <SidebarGroupLabel class="text-muted-foreground tracking-tight"
          >Post Settings</SidebarGroupLabel
        >
        <SidebarGroupContent class="mt-2 grid grid-cols-1 gap-y-3">
          <div
            v-for="(n, index) in 10"
            :key="index"
            class="bg-pattern-diagonal border-border/50 aspect-4/3 rounded-xl border"
          ></div>
        </SidebarGroupContent>
      </SidebarGroup>
    </SidebarContent>

    <SidebarRail />
  </Sidebar>
</template>

<script setup>
const props = defineProps({
  collapsible: {
    type: String,
    default: "offcanvas",
  },
});
</script>
