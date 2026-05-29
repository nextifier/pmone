<script setup lang="ts">
import { cn } from "@/lib/utils";
import { TabsContent, type TabsContentProps } from "reka-ui";
import { computed, type HTMLAttributes } from "vue";

const props = defineProps<
  TabsContentProps & { class?: HTMLAttributes["class"] }
>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;
  return delegated;
});
</script>

<template>
  <TabsContent
    v-bind="delegatedProps"
    force-mount
    data-slot="tabs-carousel-panel"
    :class="
      cn(
        'box-border w-full shrink-0 snap-start snap-always ring-offset-background focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
        props.class,
      )
    "
  >
    <slot />
  </TabsContent>
</template>
