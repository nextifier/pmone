<template>
  <div class="mx-auto max-w-3xl space-y-6 pt-4 pb-16">
    <div v-if="pending" class="flex justify-center py-12">
      <Spinner class="size-8" />
    </div>

    <div v-else-if="error" class="border-border bg-card rounded-lg border p-12 text-center">
      <Icon name="lucide:alert-circle" class="text-destructive mx-auto mb-3 size-12" />
      <p class="text-muted-foreground text-sm">Failed to load task. Please try again.</p>
      <Button variant="outline" size="sm" class="mt-4" @click="$router.back()">
        <Icon name="lucide:arrow-left" class="size-4" />
        <span>Go Back</span>
      </Button>
    </div>

    <div v-else-if="task" class="space-y-6">
      <div class="flex items-center justify-between">
        <div class="space-y-1">
          <h1 class="text-2xl font-bold tracking-tight">Edit Task</h1>
          <p class="text-muted-foreground text-sm">Update the details of your task below.</p>
        </div>
        <BackButton />
      </div>

      <FormTask :task="task" @submit="handleUpdate" @cancel="handleCancel" :loading="updating" />
    </div>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import BackButton from "@/components/BackButton.vue";
import FormTask from "@/components/FormTask.vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const router = useRouter();
const ulid = route.params.ulid;

const updating = ref(false);

// Fetch task details
const { data: response, pending, error } = await useLazySanctumFetch(`/api/tasks/${ulid}`, {
  key: `task-edit-${ulid}`,
});
const task = computed(() => response.value?.data);

const handleUpdate = async (formData) => {
  updating.value = true;
  try {
    const client = useSanctumClient();
    const res = await client(`/api/tasks/${ulid}`, {
      method: 'PUT',
      body: formData,
    });

    toast.success('Task updated successfully');

    // Redirect to task detail page
    await router.push(`/tasks/${res.data.ulid}`);
  } catch (err) {
    console.error('Failed to update task:', err);
    toast.error('Failed to update task');
    updating.value = false;
  }
};

const handleCancel = () => {
  router.push(`/tasks/${ulid}`);
};

// Set page meta
useHead({
  title: computed(() => task.value ? `Edit ${task.value.title} - Tasks` : 'Edit Task'),
});
</script>
