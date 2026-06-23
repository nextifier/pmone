<template>
  <div class="rounded-lg border">
    <div class="flex items-center justify-between border-b px-4 py-3">
      <div class="flex items-center gap-x-1.5">
        <Icon name="hugeicons:sticky-note-01" class="size-4 shrink-0" />
        <h2 class="text-sm font-medium tracking-tight">Internal notes</h2>
      </div>
      <span class="text-muted-foreground text-sm tracking-tight">Staff only</span>
    </div>

    <div class="p-4">
      <form class="flex flex-col gap-y-2" @submit.prevent="addNote">
        <Textarea
          v-model="draft"
          :rows="2"
          placeholder="Add a private note about this user..."
          class="resize-y"
        />
        <div class="flex justify-end">
          <Button type="submit" size="sm" :disabled="!draft.trim() || saving">
            <Spinner v-if="saving" class="size-4" />
            <span>Add note</span>
          </Button>
        </div>
      </form>

      <div v-if="loading" class="text-muted-foreground mt-4 text-sm tracking-tight">Loading notes...</div>

      <div v-else-if="notes.length === 0" class="text-muted-foreground mt-4 text-sm tracking-tight">
        No notes yet.
      </div>

      <ul v-else class="mt-4 flex flex-col gap-y-3">
        <li v-for="note in notes" :key="note.id" class="flex items-start gap-x-3">
          <Avatar v-if="note.author" :model="note.author" class="size-7 shrink-0" rounded="rounded-full" />
          <div class="min-w-0 flex-1">
            <p class="text-sm tracking-tight whitespace-pre-wrap">{{ note.body }}</p>
            <div class="text-muted-foreground mt-0.5 flex items-center gap-x-1.5 text-sm tracking-tight">
              <span>{{ note.author?.name || "Unknown" }}</span>
              <span>·</span>
              <span v-tippy="$dayjs(note.created_at).format('MMMM D, YYYY [at] h:mm A')">{{ note.time_ago }}</span>
            </div>
          </div>
          <Button
            v-tippy="'Delete note'"
            variant="ghost"
            size="iconSm"
            class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 shrink-0"
            @click="deleteNote(note)"
          >
            <Icon name="lucide:trash" class="size-3.5" />
          </Button>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { Avatar } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "vue-sonner";

const props = defineProps({
  username: { type: String, required: true },
});

const { $dayjs } = useNuxtApp();
const client = useSanctumClient();

const notes = ref([]);
const loading = ref(true);
const saving = ref(false);
const draft = ref("");

async function fetchNotes() {
  loading.value = true;
  try {
    const res = await client(`/api/users/${props.username}/notes`);
    notes.value = res.data || [];
  } catch (err) {
    console.error("Error loading notes:", err);
  } finally {
    loading.value = false;
  }
}

async function addNote() {
  if (!draft.value.trim()) return;
  saving.value = true;
  try {
    const res = await client(`/api/users/${props.username}/notes`, {
      method: "POST",
      body: { body: draft.value.trim() },
    });
    notes.value.unshift(res.data);
    draft.value = "";
  } catch (err) {
    toast.error("Failed to add note", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    saving.value = false;
  }
}

async function deleteNote(note) {
  try {
    await client(`/api/users/${props.username}/notes/${note.id}`, { method: "DELETE" });
    notes.value = notes.value.filter((n) => n.id !== note.id);
  } catch (err) {
    toast.error("Failed to delete note", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  }
}

onMounted(fetchNotes);
</script>
