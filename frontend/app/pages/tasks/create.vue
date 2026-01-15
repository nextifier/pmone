<template>
  <div class="mx-auto max-w-3xl space-y-6 pt-4 pb-16">
    <div class="flex items-center justify-between">
      <div class="space-y-1">
        <h1 class="text-2xl font-bold tracking-tight">Create New Task</h1>
        <p class="text-muted-foreground text-sm">Fill in the details below to create a new task.</p>
      </div>
    </div>

    <FormTask @submit="handleCreate" @cancel="handleCancel" :loading="creating" />
  </div>
</template>

<script setup>
import FormTask from "@/components/FormTask.vue";

definePageMeta({
  middleware: ['auth', 'verified'],
  layout: 'default',
});

const router = useRouter();
const creating = ref(false);

const handleCreate = async (formData) => {
  creating.value = true;
  try {
    const response = await $fetch('/api/tasks', {
      method: 'POST',
      body: formData,
    });

    // Redirect to task detail page
    await router.push(`/tasks/${response.data.ulid}`);

    // Show success toast (if you have toast component)
    // toast.success('Task created successfully');
  } catch (error) {
    console.error('Failed to create task:', error);
    // toast.error('Failed to create task');
    creating.value = false;
  }
};

const handleCancel = () => {
  router.back();
};
</script>
