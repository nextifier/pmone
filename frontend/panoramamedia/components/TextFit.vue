<template>
  <component :is="tag" v-if="text" class="text-fit">
    <span>
      <span class="text-fit">
        <span>
          <ScrollFloat v-if="animated" :children="text" :class="textClasses" />
          <span v-else :class="textClasses" v-text="text" />
        </span>
        <span aria-hidden="true" v-text="text" />
      </span>
    </span>
    <span aria-hidden="true" v-text="text" />
  </component>
</template>

<script setup>
defineProps({
  tag: {
    type: String,
    default: "span",
  },
  text: {
    type: String,
    required: true,
  },
  animated: {
    type: Boolean,
    default: false,
  },
  textClasses: {
    type: String,
  },
});
</script>
