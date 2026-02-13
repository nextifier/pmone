import { toast } from "vue-sonner";

export function useTaskDialogs(options: {
  refresh: () => Promise<void>;
  withCreate?: boolean;
  onAfterDelete?: (task: any) => void;
}) {
  const client = useSanctumClient();

  // ============ Edit Task Dialog ============
  const editDialogOpen = ref(false);
  const editFormRef = ref<any>(null);
  const editLoading = ref(false);
  const taskToEdit = ref<any>(null);

  const openEditDialog = (task: any) => {
    taskToEdit.value = task;
    editDialogOpen.value = true;
  };

  const handleEditTask = async (payload: any) => {
    if (!taskToEdit.value) return;

    editLoading.value = true;
    try {
      await client(`/api/tasks/${taskToEdit.value.ulid}`, {
        method: "PUT",
        body: payload,
      });

      await options.refresh();
      editFormRef.value?.resetForm();
      editDialogOpen.value = false;
      taskToEdit.value = null;
      toast.success("Task updated successfully");
    } catch (err: any) {
      console.error("Failed to update task:", err);
      if (err.response?._data?.errors) {
        editFormRef.value?.setErrors(err.response._data.errors);
      }
      toast.error(err.response?._data?.message || "Failed to update task");
    } finally {
      editLoading.value = false;
    }
  };

  // ============ Create Task Dialog ============
  const createDialogOpen = ref(false);
  const createFormRef = ref<any>(null);
  const createLoading = ref(false);

  const openCreateDialog = () => {
    createFormRef.value?.resetForm();
    createDialogOpen.value = true;
    nextTick(() => {
      createFormRef.value?.focusTitle();
    });
  };

  const handleCreateTask = async (payload: any) => {
    createLoading.value = true;
    try {
      await client("/api/tasks", {
        method: "POST",
        body: payload,
      });

      await options.refresh();
      createFormRef.value?.resetForm();
      createDialogOpen.value = false;
      toast.success("Task created successfully");
    } catch (err: any) {
      console.error("Failed to create task:", err);
      if (err.response?._data?.errors) {
        createFormRef.value?.setErrors(err.response._data.errors);
      }
      toast.error(err.response?._data?.message || "Failed to create task");
    } finally {
      createLoading.value = false;
    }
  };

  // ============ Detail Task Dialog ============
  const detailDialogOpen = ref(false);
  const taskToView = ref<any>(null);

  const openDetailDialog = (task: any) => {
    taskToView.value = task;
    detailDialogOpen.value = true;
  };

  const handleEditFromDetail = (task: any) => {
    detailDialogOpen.value = false;
    nextTick(() => {
      openEditDialog(task);
    });
  };

  // ============ Unsaved Changes Dialog ============
  const unsavedDialogOpen = ref(false);
  const unsavedContext = ref<"create" | "edit" | null>(null);
  const unsavedPayload = ref<any>(null);

  const handleEditClosePrevented = () => {
    unsavedPayload.value = editFormRef.value?.getPayload?.() || null;
    unsavedContext.value = "edit";
    unsavedDialogOpen.value = true;
  };

  const handleCreateClosePrevented = () => {
    unsavedPayload.value = createFormRef.value?.getPayload?.() || null;
    unsavedContext.value = "create";
    unsavedDialogOpen.value = true;
  };

  const handleUnsavedSave = () => {
    unsavedDialogOpen.value = false;
    if (unsavedPayload.value) {
      if (unsavedContext.value === "create") {
        handleCreateTask(unsavedPayload.value);
      } else {
        handleEditTask(unsavedPayload.value);
      }
    }
    unsavedPayload.value = null;
  };

  const handleUnsavedDiscard = () => {
    unsavedDialogOpen.value = false;
    unsavedPayload.value = null;
    if (unsavedContext.value === "create") {
      createDialogOpen.value = false;
      createFormRef.value?.resetForm();
    } else {
      editDialogOpen.value = false;
      taskToEdit.value = null;
    }
  };

  // ============ Delete Task Dialog ============
  const deleteDialogOpen = ref(false);
  const taskToDelete = ref<any>(null);
  const deleteLoading = ref(false);

  const openDeleteDialog = (task: any) => {
    taskToDelete.value = task;
    deleteDialogOpen.value = true;
  };

  const handleDeleteTask = async () => {
    if (!taskToDelete.value) return;

    deleteLoading.value = true;
    try {
      await client(`/api/tasks/${taskToDelete.value.ulid}`, {
        method: "DELETE",
      });

      const deletedTask = taskToDelete.value;
      deleteDialogOpen.value = false;
      taskToDelete.value = null;

      if (options.onAfterDelete) {
        options.onAfterDelete(deletedTask);
      } else {
        await options.refresh();
      }

      toast.success("Task deleted");
    } catch (err: any) {
      console.error("Failed to delete task:", err);
      toast.error("Failed to delete task");
    } finally {
      deleteLoading.value = false;
    }
  };

  return {
    // Edit
    editDialogOpen,
    editFormRef,
    editLoading,
    taskToEdit,
    openEditDialog,
    handleEditTask,
    handleEditClosePrevented,
    // Create
    createDialogOpen,
    createFormRef,
    createLoading,
    openCreateDialog,
    handleCreateTask,
    handleCreateClosePrevented,
    // Detail
    detailDialogOpen,
    taskToView,
    openDetailDialog,
    handleEditFromDetail,
    // Unsaved
    unsavedDialogOpen,
    handleUnsavedSave,
    handleUnsavedDiscard,
    // Delete
    deleteDialogOpen,
    taskToDelete,
    deleteLoading,
    openDeleteDialog,
    handleDeleteTask,
  };
}
