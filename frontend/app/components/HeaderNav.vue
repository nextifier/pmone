<template>
  <NavigationMenu :disable-hover-trigger="true">
    <NavigationMenuList>
      <NavigationMenuItem
        v-for="(item, index) in useAppConfig().routes.header"
        :key="index"
        class="relative"
      >
        <NavigationMenuLink v-if="!item.links" as-child>
          <NuxtLink
            :to="item.path"
            class="text-muted-foreground hover:bg-muted relative flex h-full items-center justify-center gap-x-1 rounded-2xl px-3 py-2 tracking-tight transition select-none active:scale-98"
            active-class="text-primary"
            @click="$scrollToTopIfCurrentPageIs(item.path)"
            @contextmenu="
              (event) => {
                if (item.rightClickLink) {
                  event.preventDefault();
                  navigateTo(item.rightClickLink, {
                    external: true,
                    open: { target: '_blank' },
                  });
                }
              }
            "
          >
            <span>{{ item.label }}</span>
          </NuxtLink>
        </NavigationMenuLink>

        <NavigationMenuTrigger
          v-else
          class="text-muted-foreground hover:bg-muted relative flex h-full items-center justify-center gap-x-1 rounded-2xl py-2 pr-1.5 pl-3 tracking-tight transition select-none active:scale-98"
        >
          <span>{{ item.label }}</span>
        </NavigationMenuTrigger>
        <NavigationMenuContent>
          <ul class="flex flex-col gap-y-1">
            <li v-for="(link, index) in item.links" :key="index">
              <NavigationMenuLink as-child>
                <NuxtLink
                  :to="link.path"
                  :target="link.path.startsWith('http') ? '_blank' : ''"
                  class="text-foreground hover:bg-muted flex h-full w-full items-center gap-x-1.5 rounded-xl px-4 py-1 text-sm tracking-tight transition select-none active:scale-98 sm:text-base lg:py-2"
                  active-class="text-primary bg-muted"
                  @click="$scrollToTopIfCurrentPageIs(link.path)"
                  @contextmenu="
                    (event) => {
                      if (link.rightClickLink) {
                        event.preventDefault();
                        navigateTo(link.rightClickLink, {
                          external: true,
                          open: { target: '_blank' },
                        });
                      }
                    }
                  "
                >
                  <span>{{ link.label }}</span>
                </NuxtLink>
              </NavigationMenuLink>
            </li>
          </ul>
        </NavigationMenuContent>
      </NavigationMenuItem>
    </NavigationMenuList>
  </NavigationMenu>
</template>
