<script setup>
import {
  NavigationMenu,
  NavigationMenuContent,
  NavigationMenuItem,
  NavigationMenuLink,
  NavigationMenuList,
  NavigationMenuTrigger,
} from "@/components/ui/navigation-menu";

const features = [
  { title: "Analytics", description: "Track usage in real time." },
  { title: "Reports", description: "Export shareable summaries." },
  { title: "Alerts", description: "Get notified on changes." },
  { title: "Integrations", description: "Connect your stack." },
];
</script>

<template>
  <NavigationMenu>
    <NavigationMenuList>
      <NavigationMenuItem>
        <NavigationMenuTrigger>Features</NavigationMenuTrigger>
        <NavigationMenuContent>
          <ul class="grid w-96 grid-cols-2 gap-2 p-4">
            <li v-for="feature in features" :key="feature.title">
              <NavigationMenuLink href="#" class="block space-y-1 rounded-md p-3 hover:bg-muted">
                <span class="text-sm font-medium tracking-tight">{{ feature.title }}</span>
                <span class="text-muted-foreground block text-sm tracking-tight">
                  {{ feature.description }}
                </span>
              </NavigationMenuLink>
            </li>
          </ul>
        </NavigationMenuContent>
      </NavigationMenuItem>
    </NavigationMenuList>
  </NavigationMenu>
</template>
