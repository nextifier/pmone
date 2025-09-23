<template>
  <SidebarMenu>
    <SidebarMenuItem class="tracking-tight">
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <SidebarMenuButton
            size="lg"
            class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
          >
            <AuthUserInfo :user="user" />
            <ChevronsUpDown class="ml-auto size-4" />
          </SidebarMenuButton>
        </DropdownMenuTrigger>
        <DropdownMenuContent
          class="w-[--reka-dropdown-menu-trigger-width] min-w-56 rounded-lg tracking-tight"
          :side="isMobile ? 'bottom' : 'right'"
          align="end"
          :side-offset="4"
        >
          <DropdownMenuLabel class="p-0 font-normal">
            <NuxtLink to="/settings/profile" @click="setOpenMobile(false)">
              <AuthUserInfo :user="user" class="px-1 py-1.5" />
            </NuxtLink>
          </DropdownMenuLabel>
          <DropdownMenuSeparator />
          <DropdownMenuGroup>
            <NuxtLink to="/settings/profile" @click="setOpenMobile(false)">
              <DropdownMenuItem>
                <Icon name="hugeicons:user" class="size-4 shrink-0" />
                Account
              </DropdownMenuItem>
            </NuxtLink>
          </DropdownMenuGroup>
          <DropdownMenuSeparator />
          <DropdownMenuItem @click="logout">
            <Icon name="hugeicons:logout-05" class="size-4 shrink-0" />
            Log out
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </SidebarMenuItem>
  </SidebarMenu>
</template>

<script setup>
import { useSidebar } from '@/components/ui/sidebar'
import { ChevronsUpDown } from 'lucide-vue-next'

const { isMobile, setOpenMobile } = useSidebar()
const { user, logout } = useSanctumAuth()
</script>
