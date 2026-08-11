<template>
  <div class="mx-auto max-w-xl space-y-6">
    <div class="space-y-1">
      <h2 class="text-base font-semibold tracking-tighter">Settings</h2>
      <p class="text-muted-foreground text-sm tracking-tight">
        How the form behaves. What people see lives on the Editor tab.
      </p>
    </div>

    <FormForm
      ref="formRef"
      mode="edit"
      section="settings"
      :form="form"
      @saved="emit('refresh')"
    />
  </div>
</template>

<script setup>
import FormForm from "@/components/FormForm.vue";

defineProps({
  form: { type: Object, required: true },
});

const emit = defineEmits(["refresh"]);

const formRef = ref(null);

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => formRef.value?.handleSubmit(),
  },
});
</script>
