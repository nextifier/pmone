<template>
  <Sidebar v-bind="props">
    <SidebarHeader>
      <NuxtLink
        to="/dashboard"
        class="hover:bg-muted flex items-center gap-x-2 rounded-lg p-2 group-data-[state=collapsed]:p-0"
        @click="setOpenMobile(false)"
      >
        <div
          class="bg-sidebar-primary text-sidebar-primary-foreground squircle flex aspect-square size-10 items-center justify-center rounded-lg group-data-[state=collapsed]:size-8"
        >
          <LogoMark class="text-primary-foreground size-5 group-data-[state=expanded]:size-6" />
        </div>

        <div class="flex flex-col group-data-[state=collapsed]:hidden">
          <span class="line-clamp-1 text-base tracking-tight">PM One</span>
          <span class="line-clamp-1 text-xs">Enterprise</span>
        </div>
      </NuxtLink>
    </SidebarHeader>
    <SidebarContent>
      <AppSidebarNavMain :items="navMainGroups" />
      <!-- <AppSidebarNavProjects :projects="data.projects" /> -->
    </SidebarContent>
    <SidebarFooter>
      <AppSidebarNavUser />
    </SidebarFooter>
    <SidebarRail />
  </Sidebar>
</template>

<script setup>
import { useSidebar } from '@/components/ui/sidebar/utils'
const { setOpenMobile } = useSidebar()

const props = defineProps({
  collapsible: {
    type: String,
    default: 'icon'
  }
})

const { user } = useSanctumAuth()

const navMainGroups = computed(() => {
  const groups = [
    {
      label: 'Platform',
      items: [
        {
          label: 'Dashboard',
          path: '/dashboard',
          iconName: 'hugeicons:dashboard-circle'
        },
        {
          label: 'Inbox',
          path: '/inbox',
          iconName: 'hugeicons:mail-open-love'
        },
        {
          label: 'Projects',
          path: '/projects',
          iconName: 'hugeicons:layers-01'
        },
        {
          label: 'Posts',
          path: '/posts',
          iconName: 'hugeicons:task-edit-01'
        },
        {
          label: 'Reports',
          path: '/reports',
          iconName: 'hugeicons:analysis-text-link'
        }
      ]
    }
  ]

  // Admin section - filter based on user permissions
  const adminItems = []

  // Users management - all authenticated users can view
  adminItems.push({
    label: 'Users',
    path: '/users',
    iconName: 'hugeicons:user-group'
  })

  // Activity Logs - only master and admin
  if (user.value?.roles?.some(role => ['master', 'admin'].includes(role))) {
    adminItems.push({
      label: 'Activity Logs',
      path: '/logs',
      iconName: 'hugeicons:activity-03'
    })
  }

  // Settings - always available
  adminItems.push({
    label: 'Settings',
    path: '/settings',
    iconName: 'hugeicons:settings-01',
    isActive: true,
    items: [
      {
        label: 'Profile',
        path: '/settings/profile'
      },
      {
        label: 'Password',
        path: '/settings/password'
      },
      {
        label: 'Appearance',
        path: '/settings/appearance'
      }
    ]
  })

  if (adminItems.length > 0) {
    groups.push({
      label: 'Admin',
      items: adminItems
    })
  }

  return groups
})
</script>
