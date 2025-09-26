<template>
  <div
    v-if="user"
    class="border-border bg-muted text-foreground relative flex aspect-square items-center justify-center rounded-lg border text-center"
  >
    <img
      v-if="user.profile_image"
      :src="user.profile_image?.sm ?? user.profile_image?.original"
      :alt="user?.name"
      class="pointer-events-none size-full rounded-lg object-cover select-none"
      width="100"
      height="100"
      loading="lazy"
      referrerPolicy="no-referrer"
    />
    <div v-else>
      <span class="text-sm tracking-wide">{{ initial }}</span>
    </div>

    <span
      v-if="showIndicator"
      class="ring-background bg-success absolute -right-0.5 -bottom-0.5 size-2 rounded-full ring-2"
    ></span>
  </div>
</template>

<script setup>
const props = defineProps({
  user: Object,
  showIndicator: {
    type: Boolean,
    default: false,
  },
});

const initial = computed(() => {
  let names = props?.user?.name?.split(" "),
    initials = names[0].substring(0, 1).toUpperCase();
  if (names.length == 1) {
    initials += names[0].substring(1, 2).toUpperCase();
  } else if (names.length > 1) {
    initials += names[names.length - 1].substring(0, 1).toUpperCase();
  }
  return initials;
});
</script>

<style></style>
