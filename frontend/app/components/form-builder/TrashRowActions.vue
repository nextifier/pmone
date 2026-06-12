<template>
  <div class="flex justify-end">
    <Popover>
      <PopoverTrigger asChild>
        <button
          class="hover:bg-muted data-[state=open]:bg-muted inline-flex size-8 items-center justify-center rounded-md"
          aria-label="Trash actions"
        >
          <Icon name="lucide:ellipsis" class="size-4" />
        </button>
      </PopoverTrigger>
      <PopoverContent align="end" class="w-44 p-1">
        <div class="flex flex-col">
          <PopoverClose asChild>
            <button
              class="hover:bg-muted flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
              @click="restoreDialogOpen = true"
            >
              <Icon name="lucide:undo-2" class="size-4 shrink-0" />
              <span>Restore</span>
            </button>
          </PopoverClose>
          <PopoverClose asChild>
            <button
              class="hover:bg-destructive/10 text-destructive flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
              @click="deleteDialogOpen = true"
            >
              <Icon name="lucide:trash" class="size-4 shrink-0" />
              <span>Delete permanently</span>
            </button>
          </PopoverClose>
        </div>
      </PopoverContent>
    </Popover>

    <ConfirmDialog
      v-model:open="restoreDialogOpen"
      title="Restore form?"
      description="The form will move back to your forms list."
      confirm-label="Restore"
      :pending="restorePending"
      @confirm="handleConfirmRestore"
    />

    <ConfirmDialog
      v-model:open="deleteDialogOpen"
      title="Delete permanently?"
      description="This action can't be undone. The form and all of its responses will be permanently deleted."
      confirm-label="Delete permanently"
      variant="destructive"
      :pending="deletePending"
      @confirm="handleConfirmDelete"
    />
  </div>
</template>

<script setup>
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { PopoverClose } from "reka-ui";

const props = defineProps({
  form: { type: Object, required: true },
  onRestore: { type: Function, required: true },
  onDelete: { type: Function, required: true },
});

const restoreDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const restorePending = ref(false);
const deletePending = ref(false);

const handleConfirmRestore = async () => {
  restorePending.value = true;
  try {
    await props.onRestore(props.form);
    restoreDialogOpen.value = false;
  } finally {
    restorePending.value = false;
  }
};

const handleConfirmDelete = async () => {
  deletePending.value = true;
  try {
    await props.onDelete(props.form);
    deleteDialogOpen.value = false;
  } finally {
    deletePending.value = false;
  }
};
</script>
