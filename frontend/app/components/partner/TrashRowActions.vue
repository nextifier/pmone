<template>
  <div class="flex justify-end gap-1">
    <button
      @click="handleRestore"
      :disabled="pending"
      class="hover:bg-muted inline-flex size-8 items-center justify-center rounded-md"
      v-tippy="'Restore'"
    >
      <Spinner v-if="restoring" class="size-4" />
      <Icon v-else name="hugeicons:undo-02" class="size-4" />
    </button>

    <DialogResponsive v-model:open="deleteOpen">
      <template #trigger="{ open }">
        <button
          class="hover:bg-muted inline-flex size-8 items-center justify-center rounded-md"
          @click="open()"
          v-tippy="'Delete permanently'"
        >
          <Icon name="hugeicons:delete-01" class="text-destructive size-4" />
        </button>
      </template>
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="page-title">Are you absolutely sure?</div>
          <p class="page-description mt-1.5">
            This action can't be undone. This will permanently delete this partner.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              @click="deleteOpen = false"
              :disabled="deleting"
            >
              Cancel
            </button>
            <button
              @click="handleForceDelete"
              :disabled="deleting"
              class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Spinner v-if="deleting" class="size-4 text-white" />
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
import { toast } from "vue-sonner";

const props = defineProps({
  partnerId: { type: Number, required: true },
});

const emit = defineEmits(["refresh"]);

const client = useSanctumClient();
const restoring = ref(false);
const deleting = ref(false);
const deleteOpen = ref(false);
const pending = computed(() => restoring.value || deleting.value);

const handleRestore = async () => {
  restoring.value = true;
  try {
    await client(`/api/partners-trash/${props.partnerId}/restore`, { method: "POST" });
    toast.success("Partner restored");
    emit("refresh");
  } catch (err) {
    toast.error("Failed to restore partner", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    restoring.value = false;
  }
};

const handleForceDelete = async () => {
  deleting.value = true;
  try {
    await client(`/api/partners-trash/${props.partnerId}`, { method: "DELETE" });
    toast.success("Partner permanently deleted");
    deleteOpen.value = false;
    emit("refresh");
  } catch (err) {
    toast.error("Failed to delete partner", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    deleting.value = false;
  }
};
</script>
