<template>
  <div class="mx-auto max-w-3xl space-y-6 pt-4 pb-16">
    <div class="flex items-center justify-between">
      <div class="space-y-1">
        <h1 class="text-2xl font-bold tracking-tight">Create New Task</h1>
        <p class="text-muted-foreground text-sm">Fill in the details below to create a new task.</p>
      </div>
      <BackButton />
    </div>

    <FormTask @submit="handleCreate" @cancel="handleCancel" :loading="creating" />
  </div>
</template>

<script setup>
import BackButton from "@/components/BackButton.vue";
import FormTask from "@/components/FormTask.vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const router = useRouter();
const creating = ref(false);

const handleCreate = async (formData) => {
  creating.value = true;
  try {
    const client = useSanctumClient();
    const response = await client('/api/tasks', {
      method: 'POST',
      body: formData,
    });

    toast.success('Task created successfully');

    // Redirect to task detail page
    await router.push(`/tasks/${response.data.ulid}`);
  } catch (err) {
    console.error('Failed to create task:', err);
    toast.error('Failed to create task');
    creating.value = false;
  }
};

const handleCancel = () => {
  router.back();
};
</script>
