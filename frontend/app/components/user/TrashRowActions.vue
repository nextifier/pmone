<template>
  <div class="flex justify-end">
    <Popover>
      <PopoverTrigger as-child>
        <button
          class="hover:bg-muted data-[state=open]:bg-muted inline-flex size-8 items-center justify-center rounded-md"
        >
          <Icon name="lucide:ellipsis" class="size-4" />
        </button>
      </PopoverTrigger>
      <PopoverContent align="end" class="w-40 p-1">
        <div class="flex flex-col">
          <PopoverClose as-child>
            <button
              class="hover:bg-muted flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
              @click="restoreDialogOpen = true"
            >
              <Icon name="lucide:undo-2" class="size-4 shrink-0" />
              <span>Restore</span>
            </button>
          </PopoverClose>

          <PopoverClose as-child>
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

    <!-- Restore Dialog -->
    <DialogResponsive v-model:open="restoreDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Restore user?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">This will restore this user.</p>
          <div class="mt-3 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              :disabled="singleRestorePending"
              @click="restoreDialogOpen = false"
            >
              Cancel
            </button>
            <button
              class="bg-primary text-primary-foreground hover:bg-primary/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="singleRestorePending"
              @click="handleRestore"
            >
              <Spinner v-if="singleRestorePending" class="size-4 text-white" />
              <span v-else>Restore</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Delete Permanently Dialog -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">
            Are you absolutely sure?
          </div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            This action can't be undone. This will permanently delete this user.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              :disabled="singleDeletePending"
              @click="deleteDialogOpen = false"
            >
              Cancel
            </button>
            <button
              class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="singleDeletePending"
              @click="handleDelete"
            >
              <Spinner v-if="singleDeletePending" class="size-4 text-white" />
              <span v-else>Delete Permanently</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { PopoverClose } from "reka-ui";
import { toast } from "vue-sonner";

const props = defineProps({
  userId: { type: Number, required: true },
});

const emit = defineEmits(["refresh"]);

const restoreDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const singleRestorePending = ref(false);
const singleDeletePending = ref(false);

const handleRestore = async () => {
  singleRestorePending.value = true;
  try {
    const client = useSanctumClient();
    const response = await client(`/api/users/trash/${props.userId}/restore`, { method: "POST" });
    emit("refresh");
    restoreDialogOpen.value = false;
    toast.success(response.message || "User restored successfully");
  } catch (error) {
    console.error("Failed to restore user:", error);
    toast.error("Failed to restore user", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    singleRestorePending.value = false;
  }
};

const handleDelete = async () => {
  singleDeletePending.value = true;
  try {
    const client = useSanctumClient();
    const response = await client(`/api/users/trash/${props.userId}`, { method: "DELETE" });
    emit("refresh");
    deleteDialogOpen.value = false;
    toast.success(response.message || "User permanently deleted");
  } catch (error) {
    console.error("Failed to permanently delete user:", error);
    toast.error("Failed to permanently delete user", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    singleDeletePending.value = false;
  }
};
</script>
