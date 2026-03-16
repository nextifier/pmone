<template>
  <div class="mx-auto flex min-h-screen max-w-lg flex-col justify-between gap-y-4 px-4 py-12">
    <!-- Title & Description -->
    <div class="flex flex-col items-center gap-y-2 text-center">
      <h1
        class="text-primary text-2xl leading-[1.2]! font-medium tracking-tighter text-balance sm:text-3xl"
      >
        {{ linkPage.title }}
      </h1>
      <p v-if="linkPage.description" class="text-body tracking-tight">
        {{ linkPage.description }}
      </p>
    </div>

    <!-- Links -->
    <div class="grid grid-cols-1 gap-y-2">
      <a
        v-for="item in activeItems"
        :key="item.id"
        :href="item.url"
        target="_blank"
        rel="noopener noreferrer"
        class="border-border hover:bg-muted flex items-center gap-3 rounded-xl border p-1 transition active:scale-98"
        @click="$emit('trackClick', item)"
      >
        <div v-if="item.poster" class="w-16 shrink-0">
          <img
            :src="item.poster.md || item.poster.url"
            :alt="item.label"
            class="w-full rounded-lg object-contain"
          />
        </div>
        <div class="flex grow flex-col gap-y-1 text-center" :class="{ 'text-left': item.poster }">
          <h2 class="text-base leading-tight! font-medium tracking-tighter">{{ item.label }}</h2>
          <p
            v-if="item.description"
            class="text-muted-foreground line-clamp-2 text-xs tracking-tight sm:text-sm"
          >
            {{ item.description }}
          </p>
        </div>
        <span class="bg-muted mr-1 flex size-9 shrink-0 items-center justify-center rounded-full">
          <Icon name="lucide:arrow-up-right" class="text-muted-foreground size-4 shrink-0" />
        </span>
      </a>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  linkPage: { type: Object, required: true },
});

defineEmits(["trackClick"]);

const activeItems = computed(() => {
  return (props.linkPage.items || []).filter((item) => item.is_active);
});
</script>
