<template>
  <div
    ref="rootEl"
    class="flex min-h-9 items-center"
    @keydown="onKeydown"
    @focusout="onFocusOut"
  >
    <InputLink
      v-if="canEdit && isEditing"
      v-model="editUrl"
      label="Website"
      class="w-full"
    />

    <button
      v-else-if="canEdit"
      type="button"
      class="flex h-9 w-full items-center truncate rounded-md text-left text-sm tracking-tight"
      :class="partner.website_url ? 'text-primary' : 'text-muted-foreground'"
      @click="startEditing"
    >
      {{ partner.website_url ? displayUrl : "Add website" }}
    </button>

    <span v-else-if="!partner.website_url" class="text-muted-foreground text-sm">-</span>
    <a
      v-else
      :href="partner.website_url"
      target="_blank"
      rel="noopener noreferrer"
      class="text-primary block max-w-48 truncate text-sm tracking-tight hover:underline"
    >
      {{ displayUrl }}
    </a>
  </div>
</template>

<script setup>
import { InputLink } from "@/components/ui/input-link";
import { toast } from "vue-sonner";

const props = defineProps({
  partner: { type: Object, required: true },
});

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canEdit = computed(() => hasPermission("partners.update"));

const displayUrl = computed(() =>
  (props.partner.website_url || "").replace(/^https?:\/\//, "")
);

// Inline edit state
const isEditing = ref(false);
const editUrl = ref("");
const saving = ref(false);
const rootEl = ref(null);

const startEditing = () => {
  editUrl.value = props.partner.website_url || "";
  isEditing.value = true;
  nextTick(() => {
    const input = rootEl.value?.querySelector("input");
    input?.focus();
    input?.select();
  });
};

const cancelEdit = () => {
  isEditing.value = false;
  editUrl.value = "";
};

const onKeydown = (e) => {
  if (!isEditing.value) {
    return;
  }
  if (e.key === "Enter") {
    e.preventDefault();
    saveUrl();
  } else if (e.key === "Escape") {
    cancelEdit();
  }
};

const onFocusOut = () => {
  if (!isEditing.value) {
    return;
  }
  // Defer so focus can settle: when entering edit mode the trigger button
  // unmounts and fires focusout before the InputLink input receives focus.
  setTimeout(() => {
    if (!isEditing.value) {
      return;
    }
    if (rootEl.value?.contains(document.activeElement)) {
      return;
    }
    saveUrl();
  }, 0);
};

const saveUrl = async () => {
  if (saving.value) {
    return;
  }

  const normalized = (editUrl.value || "").trim() || null;
  const current = props.partner.website_url || null;

  if (normalized === current) {
    cancelEdit();
    return;
  }

  saving.value = true;
  const oldUrl = props.partner.website_url;
  props.partner.website_url = normalized;
  isEditing.value = false;

  try {
    await client(`/api/partners/${props.partner.partner_slug}`, {
      method: "PUT",
      body: { website_url: normalized },
    });
    toast.success("Partner updated");
  } catch (err) {
    props.partner.website_url = oldUrl;
    toast.error("Failed to update partner", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    saving.value = false;
  }
};
</script>
