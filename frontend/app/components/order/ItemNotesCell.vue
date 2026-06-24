<template>
  <Input
    v-if="canEdit"
    ref="editInputEl"
    :model-value="isEditing ? editNote : item.internal_notes || ''"
    :readonly="!isEditing"
    placeholder="Add note"
    class="h-8 w-full min-w-0 rounded-md border px-2 py-0 text-sm tracking-tight transition-[color,background-color,border-color,box-shadow] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none"
    :class="
      isEditing
        ? 'border-ring ring-ring bg-background dark:bg-background shadow-xs ring-[1px]'
        : 'border-transparent bg-transparent shadow-none dark:bg-transparent cursor-pointer'
    "
    @update:model-value="editNote = $event"
    @click="handleClick"
    @blur="isEditing && saveNote()"
    @keydown.enter.prevent="isEditing && saveNote()"
    @keydown.escape="isEditing && cancelEdit()"
  />
  <span v-else-if="item.internal_notes" class="text-muted-foreground text-sm tracking-tight">
    {{ item.internal_notes }}
  </span>
  <span v-else class="text-muted-foreground text-sm">-</span>
</template>

<script setup>
import { Input } from "@/components/ui/input";
import { toast } from "vue-sonner";

const props = defineProps({
  item: { type: Object, required: true },
  orderBase: { type: String, required: true },
  canEdit: { type: Boolean, default: false },
});

const client = useSanctumClient();

const isEditing = ref(false);
const editNote = ref("");
const saving = ref(false);
const editInputEl = ref(null);

const focusInput = () => {
  const el = editInputEl.value?.$el ?? editInputEl.value;
  el?.focus?.();
  el?.select?.();
};

const handleClick = () => {
  if (!isEditing.value) {
    startEditing();
  }
};

const startEditing = () => {
  editNote.value = props.item.internal_notes || "";
  isEditing.value = true;
  nextTick(focusInput);
};

const cancelEdit = () => {
  isEditing.value = false;
  editNote.value = "";
};

const saveNote = async () => {
  if (saving.value) {
    return;
  }

  const normalized = editNote.value.trim() || null;
  const current = props.item.internal_notes || null;

  if (normalized === current) {
    cancelEdit();
    return;
  }

  saving.value = true;
  const oldNote = props.item.internal_notes;
  props.item.internal_notes = normalized;
  isEditing.value = false;

  try {
    await client(`${props.orderBase}/internal-notes`, {
      method: "PATCH",
      body: { items: [{ id: props.item.id, internal_notes: normalized }] },
    });
    toast.success("Internal note saved");
  } catch (err) {
    props.item.internal_notes = oldNote;
    toast.error("Failed to save note", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    saving.value = false;
  }
};
</script>
