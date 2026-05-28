<script setup>
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarInset,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar";

const items = [
  { title: "Home", icon: "lucide:home" },
  { title: "Inbox", icon: "lucide:inbox" },
  { title: "Calendar", icon: "lucide:calendar" },
  { title: "Search", icon: "lucide:search" },
  { title: "Settings", icon: "lucide:settings" },
];
</script>

<template>
  <SidebarProvider :style="{ '--sidebar-width': '14rem' }" class="h-72 w-full overflow-hidden rounded-lg border">
    <Sidebar collapsible="none" class="static">
      <SidebarHeader>
        <span class="px-2 text-sm font-semibold tracking-tight">Acme Inc</span>
      </SidebarHeader>
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel>Workspace</SidebarGroupLabel>
          <SidebarMenu>
            <SidebarMenuItem v-for="i in items" :key="i.title">
              <SidebarMenuButton>
                <Icon :name="i.icon" />
                <span>{{ i.title }}</span>
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarGroup>
      </SidebarContent>
    </Sidebar>
    <SidebarInset>
      <div class="flex items-center gap-2 p-3">
        <SidebarTrigger />
        <span class="text-sm tracking-tight">Page content</span>
      </div>
    </SidebarInset>
  </SidebarProvider>
</template>
