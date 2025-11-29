<template>
  <nuxt-link
    :to="to"
    :target="to.startsWith('http') ? '_blank' : ''"
    class="text-primary hover:bg-muted relative flex size-9 items-center justify-center rounded-xl transition active:scale-98"
    :aria-label="label"
    v-tippy="label"
  >
    <Icon :name="iconName" :class="iconClasses" />
  </nuxt-link>
</template>

<script setup>
const props = defineProps({
  label: {
    type: String,
  },
  to: {
    type: String,
  },
  openInNewTab: {
    type: Boolean,
    default: true,
  },
  iconName: {
    type: String,
  },
  iconClasses: {
    type: String,
    default: "size-4.5",
  },
});
</script>
