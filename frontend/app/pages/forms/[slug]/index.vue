<template>
  <div class="mx-auto max-w-xl">
    <FormForm ref="formRef" mode="edit" :form="form" @saved="emit('refresh')" />
  </div>
</template>

<script setup>
const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(["refresh"]);
const formRef = ref(null);

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      formRef.value?.handleSubmit();
    },
  },
});
</script>
