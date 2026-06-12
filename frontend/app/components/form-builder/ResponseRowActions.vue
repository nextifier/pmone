<template>
  <div class="flex justify-end">
    <Popover>
      <PopoverTrigger asChild>
        <button
          class="hover:bg-muted data-[state=open]:bg-muted inline-flex size-8 items-center justify-center rounded-md"
          aria-label="Response actions"
        >
          <Icon name="lucide:ellipsis" class="size-4" />
        </button>
      </PopoverTrigger>
      <PopoverContent align="end" class="w-44 p-1">
        <div class="flex flex-col">
          <PopoverClose asChild>
            <button
              class="hover:bg-muted flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
              @click="onView(response)"
            >
              <Icon name="lucide:eye" class="size-4 shrink-0" />
              <span>View details</span>
            </button>
          </PopoverClose>
          <div class="border-border my-1 border-t" />
          <PopoverClose v-for="s in RESPONSE_STATUS_OPTIONS" :key="s.value" asChild>
            <button
              class="hover:bg-muted flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
              @click="onSetStatus(response, s.value)"
            >
              <Icon :name="s.icon" class="size-4 shrink-0" :class="s.color" />
              <span>{{ s.label }}</span>
            </button>
          </PopoverClose>
          <div class="border-border my-1 border-t" />
          <PopoverClose asChild>
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
      title="Delete response?"
      description="This action can't be undone. The response will be permanently deleted."
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
import { RESPONSE_STATUS_OPTIONS } from "@/lib/formBuilderStatus";
import { PopoverClose } from "reka-ui";

const props = defineProps({
  response: { type: Object, required: true },
  onView: { type: Function, required: true },
  onSetStatus: { type: Function, required: true },
  onDelete: { type: Function, required: true },
});

const deleteDialogOpen = ref(false);
const deletePending = ref(false);

const handleConfirmDelete = async () => {
  deletePending.value = true;
  try {
    await props.onDelete(props.response);
    deleteDialogOpen.value = false;
  } finally {
    deletePending.value = false;
  }
};
</script>
