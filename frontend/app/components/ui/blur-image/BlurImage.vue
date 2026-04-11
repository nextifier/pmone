<template>
  <div
    class="blur-image relative size-full overflow-hidden"
    :class="{ 'is-loaded': isLoaded }"
    :style="wrapperStyle"
  >
    <div v-if="!lqipLoaded" class="skeleton absolute inset-0" />

    <img
      v-if="lqip"
      ref="lqipRef"
      :src="lqip"
      alt=""
      aria-hidden="true"
      class="absolute inset-0 size-full scale-110 object-cover blur-lg transition-opacity duration-300 ease-in-out"
      :class="isLoaded ? 'opacity-0' : 'opacity-100'"
      @load="onLqipLoad"
    />

    <div
      v-if="lqipLoaded && !isLoaded"
      class="shimmer-overlay absolute inset-0 z-10"
    />

    <img
      ref="imgRef"
      :src="src"
      :alt="alt"
      :width="width"
      :height="height"
      :loading="loading"
      class="relative size-full transition-opacity duration-300 ease-in-out"
      :class="[imageClass, isLoaded ? 'opacity-100' : 'opacity-0']"
      :style="imageStyle"
      v-bind="$attrs"
      @load="onLoad"
    />
  </div>
</template>

<script setup>
defineOptions({ inheritAttrs: false });

const props = defineProps({
  src: { type: String, required: true },
  lqip: { type: String, default: "" },
  alt: { type: String, default: "" },
  width: { type: [Number, String], default: undefined },
  height: { type: [Number, String], default: undefined },
  loading: { type: String, default: "lazy" },
  imageClass: { type: [String, Object, Array], default: "" },
  imageStyle: { type: [String, Object], default: undefined },
});

const imgRef = ref(null);
const lqipRef = ref(null);
const isLoaded = ref(false);
const lqipLoaded = ref(false);

const wrapperStyle = computed(() => {
  if (props.width && props.height) {
    return { aspectRatio: `${props.width} / ${props.height}` };
  }
  return {};
});

const onLqipLoad = () => {
  lqipLoaded.value = true;
};

const onLoad = () => {
  isLoaded.value = true;
};

onMounted(() => {
  if (lqipRef.value?.complete && lqipRef.value?.naturalWidth > 0) {
    lqipLoaded.value = true;
  }
  if (imgRef.value?.complete && imgRef.value?.naturalWidth > 0) {
    isLoaded.value = true;
  }
});
</script>
