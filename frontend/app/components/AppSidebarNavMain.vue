<template>
  <SidebarGroup v-for="(navGroup, index) in navMainGroups" :key="index">
    <SidebarGroupLabel class="text-muted-foreground tracking-tight">{{
      navGroup.label
    }}</SidebarGroupLabel>
    <SidebarMenu>
      <div v-for="item in navGroup.items" :key="item.label" class="tracking-tight">
        <Collapsible
          v-if="item.items?.length"
          as-child
          :default-open="item.isActive"
          class="group/collapsible"
        >
          <SidebarMenuItem>
            <CollapsibleTrigger as-child>
              <SidebarMenuButton :tooltip="item.label">
                <Icon v-if="item.iconName" :name="item.iconName" class="size-4.5! shrink-0" />
                <span>{{ item.label }}</span>
                <ChevronRight
                  class="text-muted-foreground! ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                />
              </SidebarMenuButton>
            </CollapsibleTrigger>
            <CollapsibleContent>
              <SidebarMenuSub>
                <SidebarMenuSubItem v-for="subItem in item.items" :key="subItem.label">
                  <SidebarMenuSubButton as-child>
                    <NuxtLink
                      :to="subItem.path"
                      :target="subItem.path.startsWith('http') ? '_blank' : ''"
                      @click="setOpenMobile(false)"
                      activeClass="bg-muted"
                      >{{ subItem.label }}</NuxtLink
                    >
                  </SidebarMenuSubButton>
                </SidebarMenuSubItem>
              </SidebarMenuSub>
            </CollapsibleContent>
          </SidebarMenuItem>
        </Collapsible>
        <NuxtLink
          v-else
          :to="item.path"
          :target="item.path.startsWith('http') ? '_blank' : ''"
          @click="
            setOpenMobile(false);
            $scrollToTopIfCurrentPageIs(item.path);
          "
          activeClass="*:bg-muted"
        >
          <SidebarMenuButton :tooltip="item.label">
            <Icon v-if="item.iconName" :name="item.iconName" class="!size-4.5 shrink-0" />
            <span>{{ item.label }}</span>
          </SidebarMenuButton>
        </NuxtLink>
      </div>
    </SidebarMenu>
  </SidebarGroup>
</template>

<script setup>
import { useSidebar } from "@/components/ui/sidebar/utils";
import { ChevronRight } from "lucide-vue-next";
const { setOpenMobile } = useSidebar();
const { user } = useSanctumAuth();

const navMainGroups = computed(() => {
  const groups = [
    {
      label: "Platform",
      items: [
        {
          label: "Dashboard",
          path: "/dashboard",
          iconName: "hugeicons:dashboard-circle",
        },
        {
          label: "Inbox",
          path: "/inbox",
          iconName: "hugeicons:mail-open-love",
        },
        {
          label: "Projects",
          path: "/projects",
          iconName: "hugeicons:layers-01",
        },
        {
          label: "Posts",
          path: "/posts",
          iconName: "hugeicons:task-edit-01",
        },

        {
          label: "Short Links & Dynamic QR Code",
          path: "/short-links",
          iconName: "hugeicons:unlink-02",
        },
        {
          label: "Static QR Code Generator",
          path: "/qr",
          iconName: "hugeicons:qr-code",
        },
        {
          label: "Web Analytics",
          path: "/web-analytics",
          iconName: "hugeicons:analysis-text-link",
        },
        {
          label: "API Consumers",
          path: "/api-consumers",
          iconName: "hugeicons:api",
        },
      ],
    },
  ];

  // Admin section - filter based on user permissions
  const adminItems = [];

  if (user.value?.roles?.some((role) => ["master", "admin", "staff"].includes(role))) {
    adminItems.push({
      label: "Users",
      path: "/users",
      iconName: "hugeicons:user-group",
    });
  }

  if (user.value?.roles?.includes("master")) {
    adminItems.push({
      label: "Roles & Permissions",
      path: "/roles",
      iconName: "hugeicons:user-settings-01",
    });
  }

  if (user.value?.roles?.some((role) => ["master", "admin"].includes(role))) {
    adminItems.push({
      label: "Google Analytics Properties",
      path: "/ga-properties",
      iconName: "hugeicons:analytics-01",
    });
  }

  // Activity Logs - only master and admin
  if (user.value?.roles?.some((role) => ["master", "admin"].includes(role))) {
    adminItems.push({
      label: "Activity Logs",
      path: "/logs",
      iconName: "hugeicons:activity-03",
    });
  }

  // Settings - always available
  adminItems.push({
    label: "Settings",
    path: "/settings",
    iconName: "hugeicons:settings-01",
    isActive: false,
    items: [
      {
        label: "Profile",
        path: "/settings/profile",
      },
      {
        label: "Password",
        path: "/settings/password",
      },
      {
        label: "Appearance",
        path: "/settings/appearance",
      },
    ],
  });

  if (adminItems.length > 0) {
    groups.push({
      label: "Admin",
      items: adminItems,
    });
  }

  return groups;
});
</script>
