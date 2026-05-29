<script setup>
import {
  Tabs,
  TabsCarousel,
  TabsCarouselPanel,
  TabsIndicator,
  TabsList,
  TabsSelectionIndicator,
  TabsTrigger,
} from "@/components/ui/tabs";

const tabs = ["home", "files", "search", "settings"];
</script>

<template>
  <Tabs default-value="home" variant="pill" swipeable class="flex w-full max-w-sm flex-col gap-4">
    <TabsList>
      <TabsIndicator class="supports-[animation-timeline:--x]:hidden" />
      <TabsSelectionIndicator />
      <TabsTrigger value="home">Home</TabsTrigger>
      <TabsTrigger value="files">Files</TabsTrigger>
      <TabsTrigger value="search">Search</TabsTrigger>
      <TabsTrigger value="settings">Settings</TabsTrigger>
    </TabsList>

    <TabsCarousel>
      <TabsCarouselPanel
        v-for="tab in tabs"
        :key="tab"
        :value="tab"
        class="px-1 text-sm tracking-tight capitalize"
      >
        <p class="font-medium">{{ tab }}</p>
        <p class="mt-1 text-muted-foreground normal-case">
          Swipe or scroll horizontally to move between panels. The indicator
          follows the scroll position where supported.
        </p>
      </TabsCarouselPanel>
    </TabsCarousel>
  </Tabs>
</template>
