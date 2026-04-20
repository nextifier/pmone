<template>
  <nav
    class="no-scrollbar bg-background scroll-fade-x relative sticky top-(--navbar-height-mobile) z-10 -mx-4 flex gap-x-5 overflow-x-auto px-4 sm:mx-0 sm:px-0 lg:top-(--navbar-height-desktop)"
  >
    <NuxtLink
      v-for="(tab, index) in tabs"
      :key="tab.to"
      :ref="(el) => (tabRefs[index] = el?.$el || el)"
      :to="tab.to"
      :class="[
        'relative flex shrink-0 items-center justify-center gap-x-1.5 py-3 text-sm font-medium tracking-tight transition select-none',
        isActive(tab) ? 'text-foreground' : 'text-muted-foreground hover:text-foreground',
      ]"
    >
      <Icon v-if="tab.icon" :name="tab.icon" class="size-4 shrink-0" />
      {{ tab.label }}
    </NuxtLink>

    <span
      v-if="indicatorStyle"
      class="bg-foreground absolute bottom-0 h-0.5 rounded-full transition-[left,width] duration-300 ease-in-out"
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
});

const route = useRoute();
const tabRefs = ref([]);
const indicatorStyle = ref(null);

const isActive = (tab) => {
  if (tab.exact) {
    return route.path === tab.to || route.path === `${tab.to}/`;
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
