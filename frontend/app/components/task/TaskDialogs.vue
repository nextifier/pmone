<template>
  <!-- Create Task Dialog -->
  <DialogResponsive
    v-if="withCreate"
    v-model:open="createDialogOpen"
    dialog-max-width="600px"
    :overflow-content="true"
    :prevent-close="createFormRef?.isDirty ?? false"
    @close-prevented="handleCreateClosePrevented"
  >
    <template #sticky-header>
      <div
        class="border-border sticky top-0 z-10 -mt-4 border-b px-4 pb-2 text-center md:mt-0 md:px-6 md:py-3.5 md:text-left"
      >
        <div class="text-lg font-semibold tracking-tighter">Create New Task</div>
      </div>
    </template>
    <template #default>
      <div class="px-4 py-4 md:px-6">
        <FormTask
          ref="createFormRef"
          :loading="createLoading"
          @submit="handleCreateTask"
          @cancel="createDialogOpen = false"
        />
      </div>
    </template>
  </DialogResponsive>

  <!-- Edit Task Dialog -->
  <DialogResponsive
    v-model:open="editDialogOpen"
    dialog-max-width="600px"
    :overflow-content="true"
    :prevent-close="editFormRef?.isDirty ?? false"
    @close-prevented="handleEditClosePrevented"
  >
    <template #sticky-header>
      <div
        class="border-border sticky top-0 z-10 -mt-4 border-b px-4 pb-2 text-center md:mt-0 md:px-6 md:py-3.5 md:text-left"
      >
        <div class="text-lg font-semibold tracking-tighter">Edit Task</div>
      </div>
    </template>
    <template #default>
      <div class="px-4 py-4 md:px-6">
        <FormTask
          v-if="taskToEdit"
          ref="editFormRef"
          :task="taskToEdit"
          :loading="editLoading"
          @submit="handleEditTask"
          @cancel="editDialogOpen = false"
        />
      </div>
    </template>
  </DialogResponsive>

  <!-- Unsaved Changes Dialog -->
  <DialogResponsive v-model:open="unsavedDialogOpen" :hide-overlay="true">
    <template #default>
      <div class="px-4 pb-10 md:px-6 md:py-6">
        <div class="text-foreground text-lg font-semibold tracking-tight">Unsaved Changes</div>
        <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
          You have unsaved changes. Would you like to save them before closing?
        </p>
        <div class="mt-4 flex justify-end gap-2">
          <Button variant="outline" @click="handleUnsavedDiscard">Discard</Button>
          <Button @click="handleUnsavedSave">Save</Button>
        </div>
      </div>
    </template>
  </DialogResponsive>

  <!-- Detail Task Dialog -->
  <DialogResponsive
    v-model:open="detailDialogOpen"
    dialog-max-width="600px"
    :overflow-content="true"
  >
    <template #default>
      <TaskDetailDialog
        v-if="taskToView"
        :task="taskToView"
        :can-edit="taskToView.can_edit !== false"
        @close="detailDialogOpen = false"
        @edit="handleEditFromDetail"
      />
    </template>
  </DialogResponsive>

  <!-- Delete Dialog -->
  <DialogResponsive v-model:open="deleteDialogOpen">
    <template #default>
      <div class="px-4 pb-10 md:px-6 md:py-6">
        <template v-if="deleteMode === 'clear-completed'">
          <div class="text-foreground text-lg font-semibold tracking-tight">
            Clear Completed Tasks?
          </div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            Are you sure you want to delete
            <strong>{{ deletableCompletedCount }} completed tasks</strong>? This action can be
            undone from trash.
          </p>
        </template>
        <template v-else>
          <div class="text-foreground text-lg font-semibold tracking-tight">Delete Task?</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            Are you sure you want to delete <strong>{{ taskToDelete?.title }}</strong
            >? This action can be undone from trash.
          </p>
        </template>
        <div class="mt-4 flex justify-end gap-2">
          <Button variant="outline" @click="deleteDialogOpen = false"> Cancel </Button>
          <Button
            variant="destructive"
            @click="deleteMode === 'clear-completed' ? $emit('clearCompleted') : handleDeleteTask()"
            :disabled="deleteLoading"
          >
            <Spinner v-if="deleteLoading" class="size-4" />
            <span v-else>Delete</span>
          </Button>
        </div>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import FormTask from "@/components/FormTask.vue";
import TaskDetailDialog from "@/components/task/TaskDetailDialog.vue";
import { Button } from "@/components/ui/button";

const props = defineProps({
  dialogs: { type: Object, required: true },
  withCreate: { type: Boolean, default: false },
  deleteMode: { type: String, default: "single" },
  deletableCompletedCount: { type: Number, default: 0 },
});

defineEmits(["clearCompleted"]);

const {
  // Edit
  editDialogOpen,
  editFormRef,
  editLoading,
  taskToEdit,
  handleEditTask,
  handleEditClosePrevented,
  // Create
  createDialogOpen,
  createFormRef,
  createLoading,
  handleCreateTask,
  handleCreateClosePrevented,
  // Detail
  detailDialogOpen,
  taskToView,
  handleEditFromDetail,
  // Unsaved
  unsavedDialogOpen,
  handleUnsavedSave,
  handleUnsavedDiscard,
  // Delete
  deleteDialogOpen,
  taskToDelete,
  deleteLoading,
  handleDeleteTask,
} = props.dialogs;
</script>
