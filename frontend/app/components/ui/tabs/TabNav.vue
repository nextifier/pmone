<template>
  <nav
    :class="[
      'no-scrollbar bg-background scroll-fade-x relative z-10 -mx-4 flex h-(--tabnav-height) shrink-0 gap-x-5 overflow-x-auto px-4 sm:mx-0 sm:px-0',
      // Nothing in the falsy branch on purpose. `relative` above is what the
      // indicator measures itself against, and a `static` here would land in
      // the same Tailwind position group and win the cascade - the indicator
      // then anchors to some ancestor further up and disappears off the nav.
      props.sticky && 'sticky top-(--navbar-height-mobile) lg:top-(--navbar-height-desktop)',
    ]"
  >
    <NuxtLink
      v-for="(tab, index) in tabs"
      :key="tab.to"
      :ref="(el) => (tabRefs[index] = el?.$el || el)"
      :to="tab.to"
      :class="[
        'relative flex shrink-0 items-center justify-center gap-x-1.5 py-3 text-sm font-medium tracking-tight transition-colors select-none',
        isActive(tab) ? 'text-foreground' : 'text-muted-foreground hover:text-foreground',
      ]"
    >
      <Icon v-if="tab.icon" :name="tab.icon" class="size-4 shrink-0" />
      {{ tab.label }}
    </NuxtLink>

    <span
      v-if="indicatorStyle"
      class="bg-foreground absolute bottom-0 h-0.5 rounded-full transition-[left,width] duration-(--tabs-dur) ease-(--tabs-ease)"
      :style="indicatorStyle"
    />
  </nav>
</template>

<script setup>
const props = defineProps({
  tabs: {
    type: Array,
    required: true,
  },
  /**
   * Turn off inside a page shell that is pinned to the viewport and therefore
   * never scrolls. The offset is measured from the nearest scrollport, and an
   * `overflow: hidden` shell becomes one, so a sticky nav there would be pushed
   * down by a second navbar height instead of staying put.
   */
  sticky: {
    type: Boolean,
    default: true,
  },
});

const route = useRoute();
const tabRefs = ref([]);
const indicatorStyle = ref(null);

const isActive = (tab) => {
  if (tab.exact) {
    return route.path === tab.to || route.path === `${tab.to}/`;
  }
  if (tab.notFor?.some((path) => route.path.startsWith(path))) {
    return false;
  }
  if (tab.activeFor?.some((path) => route.path.startsWith(path))) {
    return true;
  }
  return route.path.startsWith(tab.to);
};

const updateIndicator = () => {
  const activeIndex = props.tabs.findIndex((tab) => isActive(tab));
  const activeEl = tabRefs.value[activeIndex];
  if (!activeEl) {
    indicatorStyle.value = null;
    return;
  }

  indicatorStyle.value = {
    left: `${activeEl.offsetLeft}px`,
    width: `${activeEl.offsetWidth}px`,
  };
};

onMounted(() => {
  nextTick(updateIndicator);
});

watch(
  () => route.path,
  () => {
    nextTick(updateIndicator);
  }
);
</script>
