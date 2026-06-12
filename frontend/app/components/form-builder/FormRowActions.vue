<template>
  <div class="flex justify-end">
    <Popover>
      <PopoverTrigger asChild>
        <button
          class="hover:bg-muted data-[state=open]:bg-muted inline-flex size-8 items-center justify-center rounded-md"
          aria-label="Form actions"
        >
          <Icon name="lucide:ellipsis" class="size-4" />
        </button>
      </PopoverTrigger>
      <PopoverContent align="end" class="w-40 p-1">
        <div class="flex flex-col">
          <PopoverClose asChild>
            <NuxtLink
              :to="`/forms/${form.slug}`"
              class="hover:bg-muted flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
            >
              <Icon name="lucide:pencil-line" class="size-4 shrink-0" />
              <span>Edit</span>
            </NuxtLink>
          </PopoverClose>
          <PopoverClose v-if="canDuplicate" asChild>
            <button
              class="hover:bg-muted flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
              @click="onDuplicate(form)"
            >
              <Icon name="lucide:copy" class="size-4 shrink-0" />
              <span>Duplicate</span>
            </button>
          </PopoverClose>
          <PopoverClose v-if="form.can_delete" asChild>
            <button
              class="hover:bg-destructive/10 text-destructive flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
              @click="deleteDialogOpen = true"
            >
              <Icon name="lucide:trash" class="size-4 shrink-0" />
              <span>Delete</span>
            </button>
          </PopoverClose>
        </div>
      </PopoverContent>
    </Popover>

    <ConfirmDialog
      v-model:open="deleteDialogOpen"
      title="Delete form?"
      description="This will move the form to trash. You can restore it later."
      confirm-label="Delete"
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
  canDuplicate: { type: Boolean, default: false },
  onDuplicate: { type: Function, required: true },
  onDelete: { type: Function, required: true },
});

const deleteDialogOpen = ref(false);
const deletePending = ref(false);

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
