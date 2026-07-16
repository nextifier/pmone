<template>
  <span v-if="!page" class="text-muted-foreground text-sm tracking-tight">-</span>

  <!-- Master-only shortcut: impersonate the user and land on the page they last saw. -->
  <button
    v-else-if="clickable"
    v-tippy="`Impersonate and open ${page.path}`"
    type="button"
    :disabled="pending"
    class="scroll-fade-x no-scrollbar hover:text-primary flex max-w-[200px] cursor-pointer items-center gap-x-1.5 overflow-x-auto text-left transition-colors disabled:opacity-50"
    @click="impersonate(user, page.path)"
  >
    <Spinner v-if="pending" class="size-3.5 shrink-0" />
    <span class="whitespace-nowrap text-sm tracking-tight underline-offset-4 hover:underline">
      {{ page.title || page.path }}
    </span>
  </button>

  <div v-else v-tippy="page.path" class="scroll-fade-x no-scrollbar max-w-[200px] overflow-x-auto">
    <span class="whitespace-nowrap text-sm tracking-tight">{{ page.title || page.path }}</span>
  </div>
</template>

<script setup>
const props = defineProps({
  user: { type: Object, required: true },
  page: { type: Object, default: null },
});

const { canImpersonate, impersonate, pending } = useImpersonate();

const clickable = computed(() => canImpersonate(props.user));
</script>
