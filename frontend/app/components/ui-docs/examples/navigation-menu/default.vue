<script setup>
import {
  NavigationMenu,
  NavigationMenuContent,
  NavigationMenuItem,
  NavigationMenuLink,
  NavigationMenuList,
  NavigationMenuTrigger,
} from "@/components/ui/navigation-menu";
</script>

<template>
  <NavigationMenu>
    <NavigationMenuList>
      <NavigationMenuItem>
        <NavigationMenuTrigger>Products</NavigationMenuTrigger>
        <NavigationMenuContent>
          <ul class="grid w-64 gap-2 p-4">
            <li><NavigationMenuLink href="#">Analytics</NavigationMenuLink></li>
            <li><NavigationMenuLink href="#">Engagement</NavigationMenuLink></li>
            <li><NavigationMenuLink href="#">Insights</NavigationMenuLink></li>
          </ul>
        </NavigationMenuContent>
      </NavigationMenuItem>
      <NavigationMenuItem>
        <NavigationMenuTrigger>Resources</NavigationMenuTrigger>
        <NavigationMenuContent>
          <ul class="grid w-64 gap-2 p-4">
            <li><NavigationMenuLink href="#">Docs</NavigationMenuLink></li>
            <li><NavigationMenuLink href="#">Guides</NavigationMenuLink></li>
            <li><NavigationMenuLink href="#">Changelog</NavigationMenuLink></li>
          </ul>
        </NavigationMenuContent>
      </NavigationMenuItem>
    </NavigationMenuList>
  </NavigationMenu>
</template>
