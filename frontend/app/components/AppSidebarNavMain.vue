<template>
  <SidebarGroup v-for="(navGroup, index) in items" :key="index">
    <SidebarGroupLabel class="text-muted-foreground tracking-tight">{{
      navGroup.label
    }}</SidebarGroupLabel>
    <SidebarMenu>
      <div
        v-for="item in navGroup.items"
        :key="item.label"
        class="tracking-tight"
      >
        <Collapsible
          v-if="item.items?.length"
          as-child
          :default-open="item.isActive"
          class="group/collapsible"
        >
          <SidebarMenuItem>
            <CollapsibleTrigger as-child>
              <SidebarMenuButton :tooltip="item.label">
                <Icon
                  v-if="item.iconName"
                  :name="item.iconName"
                  class="!size-4.5 shrink-0"
                />
                <span>{{ item.label }}</span>
                <ChevronRight
                  class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                />
              </SidebarMenuButton>
            </CollapsibleTrigger>
            <CollapsibleContent>
              <SidebarMenuSub>
                <SidebarMenuSubItem
                  v-for="subItem in item.items"
                  :key="subItem.label"
                >
                  <SidebarMenuSubButton as-child>
                    <NuxtLink
                      :to="subItem.path"
                      :target="subItem.path.startsWith('http') ? '_blank' : ''"
                      @click="setOpenMobile(false)"
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
          @click="setOpenMobile(false)"
        >
          <SidebarMenuButton :tooltip="item.label">
            <Icon
              v-if="item.iconName"
              :name="item.iconName"
              class="!size-4.5 shrink-0"
            />
            <span>{{ item.label }}</span>
          </SidebarMenuButton>
        </NuxtLink>
      </div>
    </SidebarMenu>
  </SidebarGroup>
</template>

<script setup>
import { ChevronRight } from "lucide-vue-next";
import { useSidebar } from "@/components/ui/sidebar/utils";
const { setOpenMobile } = useSidebar();

defineProps({
  items: {
    type: Array,
    required: true,
    default: () => [],
  },
});
</script>
