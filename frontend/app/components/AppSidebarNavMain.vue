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
const { hasPermission, hasRole, isStaffOrAbove } = usePermission();
const { t } = useI18n();

const isExhibitor = computed(() => hasRole("exhibitor") && !isStaffOrAbove.value);

const isWriter = computed(() => hasRole("writer") && !isStaffOrAbove.value);

const isDefaultUser = computed(
  () => hasRole("user") && !isStaffOrAbove.value && !hasRole("exhibitor") && !hasRole("writer")
);

const navMainGroups = computed(() => {
  // Writer - focused sidebar
  if (isWriter.value) {
    return [
      {
        label: "Writer",
        items: [
          {
            label: t("nav.dashboard"),
            path: "/dashboard",
            iconName: "hugeicons:dashboard-circle",
          },
          {
            label: "Posts",
            path: "/posts",
            iconName: "hugeicons:task-edit-01",
          },
          {
            label: "Web Analytics",
            path: "/web-analytics",
            iconName: "hugeicons:analysis-text-link",
          },
          {
            label: t("nav.settings"),
            path: "/settings",
            iconName: "hugeicons:settings-01",
            isActive: false,
            items: [
              { label: t("nav.profile"), path: "/settings/profile" },
              { label: t("nav.password"), path: "/settings/password" },
              { label: t("nav.appearance"), path: "/settings/appearance" },
            ],
          },
        ],
      },
    ];
  }

  // Default user - minimal sidebar
  if (isDefaultUser.value) {
    return [
      {
        label: t("nav.exhibitor"),
        items: [
          {
            label: t("nav.dashboard"),
            path: "/dashboard",
            iconName: "hugeicons:dashboard-circle",
          },
          {
            label: t("nav.settings"),
            path: "/settings",
            iconName: "hugeicons:settings-01",
            isActive: false,
            items: [
              { label: t("nav.profile"), path: "/settings/profile" },
              { label: t("nav.password"), path: "/settings/password" },
              { label: t("nav.appearance"), path: "/settings/appearance" },
            ],
          },
        ],
      },
    ];
  }

  // Exhibitor-only sidebar
  if (isExhibitor.value) {
    return [
      {
        label: t("nav.exhibitor"),
        items: [
          {
            label: t("nav.dashboard"),
            path: "/dashboard",
            iconName: "hugeicons:dashboard-circle",
          },
          {
            label: t("nav.brands"),
            path: "/brands",
            iconName: "hugeicons:blockchain-01",
          },
          {
            label: t("nav.orders"),
            path: "/orders",
            iconName: "hugeicons:shopping-bag-01",
          },
          {
            label: t("nav.settings"),
            path: "/settings",
            iconName: "hugeicons:settings-01",
            isActive: false,
            items: [
              { label: t("nav.profile"), path: "/settings/profile" },
              { label: t("nav.password"), path: "/settings/password" },
              { label: t("nav.appearance"), path: "/settings/appearance" },
            ],
          },
        ],
      },
    ];
  }

  // Platform section - filter based on user permissions
  const platformItems = [];

  // Dashboard - accessible by all users
  platformItems.push({
    label: "Dashboard",
    path: "/dashboard",
    iconName: "hugeicons:dashboard-circle",
  });

  // Inbox - requires contact_forms.read permission
  if (hasPermission("contact_forms.read")) {
    platformItems.push({
      label: "Inbox",
      path: "/inbox",
      iconName: "hugeicons:mail-open-love",
    });
  }

  // Orders - requires orders.read permission
  if (hasPermission("orders.read")) {
    platformItems.push({
      label: "Orders",
      path: "/orders",
      iconName: "hugeicons:shopping-bag-01",
    });
  }

  // Projects - requires projects.read permission
  if (hasPermission("projects.read")) {
    platformItems.push({
      label: "Projects",
      path: "/projects",
      iconName: "hugeicons:layers-01",
    });
  }

  // Brands - requires brands.read permission
  if (hasPermission("brands.read")) {
    platformItems.push({
      label: "Brands",
      path: "/brands",
      iconName: "hugeicons:blockchain-01",
    });
  }

  // Tasks - requires tasks.read permission
  if (hasPermission("tasks.read")) {
    platformItems.push({
      label: "Tasks",
      path: "/tasks",
      iconName: "hugeicons:task-daily-01",
    });
  }

  // Short Links & Dynamic QR Code - requires short_links.read permission
  if (hasPermission("short_links.read")) {
    platformItems.push({
      label: "Short Links & Dynamic QR Code",
      path: "/links",
      iconName: "hugeicons:unlink-02",
    });
  }

  // Static QR Code Generator - accessible by all users
  platformItems.push({
    label: "Static QR Code Generator",
    path: "/qr",
    iconName: "hugeicons:qr-code",
  });

  // Posts - requires posts.read permission
  if (hasPermission("posts.read")) {
    platformItems.push({
      label: "Posts",
      path: "/posts",
      iconName: "hugeicons:task-edit-01",
    });
  }

  // Web Analytics - requires analytics.view permission
  if (hasPermission("analytics.view")) {
    platformItems.push({
      label: "Web Analytics",
      path: "/web-analytics",
      iconName: "hugeicons:analysis-text-link",
    });
  }

  const groups = [
    {
      label: "Platform",
      items: platformItems,
    },
  ];

  // Admin section - filter based on user permissions
  const adminItems = [];

  // Users - requires users.read permission
  if (hasPermission("users.read")) {
    adminItems.push({
      label: "Users",
      path: "/users",
      iconName: "hugeicons:user-group",
    });

    adminItems.push({
      label: "Exhibitor PICs",
      path: "/exhibitors",
      iconName: "hugeicons:location-user-04",
    });
  }

  // Roles - requires roles.read permission
  if (hasPermission("roles.read")) {
    adminItems.push({
      label: "Roles",
      path: "/roles",
      iconName: "hugeicons:user-settings-01",
    });
  }

  // Permissions - requires permissions.read permission
  if (hasPermission("permissions.read")) {
    adminItems.push({
      label: "Permissions",
      path: "/permissions",
      iconName: "hugeicons:shield-key",
    });
  }

  // API Consumers - requires api_consumers.read permission
  if (hasPermission("api_consumers.read")) {
    adminItems.push({
      label: "API Consumers",
      path: "/api-consumers",
      iconName: "hugeicons:api",
    });
  }

  // Google Analytics Properties - only master role
  if (hasRole("master")) {
    adminItems.push({
      label: "Google Analytics Properties",
      path: "/ga-properties",
      iconName: "hugeicons:analytics-01",
    });
  }

  // Activity Logs - requires admin.logs permission
  if (hasPermission("admin.logs")) {
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

  // Others section
  const otherItems = [
    {
      label: "Docs",
      path: "/docs",
      iconName: "hugeicons:book-open-01",
    },
    {
      label: "Colors",
      path: "/colors",
      iconName: "hugeicons:paint-board",
    },
    {
      label: "Exchange Rate",
      path: "/exchange-rate",
      iconName: "hugeicons:money-exchange-02",
    },
    {
      label: "Database Diagram",
      path: "/database-diagram",
      iconName: "hugeicons:structure-03",
    },
  ];

  groups.push({
    label: "Others",
    items: otherItems,
  });

  return groups;
});
</script>
