<template>
  <div class="flex items-center gap-x-2">
    <Lightbox
      v-if="logoItems.length"
      :items="logoItems"
      :alt="partner.partner_name"
      thumbnail-key="md"
      full-key="lg"
      :show-thumbnails="false"
      :show-counter="false"
      :show-nav-buttons="false"
      :show-caption="false"
      :show-download="false"
      :image-props="{ class: 'bg-white rounded-xl p-6' }"
    >
      <template #trigger="{ openAt }">
        <button
          type="button"
          class="relative aspect-3/2 h-10 shrink-0 cursor-zoom-in overflow-hidden rounded-lg border bg-white transition hover:opacity-80"
          @click="openAt(0)"
        >
          <img
            :src="logoUrl"
            :alt="partner.partner_name"
            class="size-full object-contain"
            loading="lazy"
            referrerpolicy="no-referrer"
          />
        </button>
      </template>
    </Lightbox>

    <NuxtLink
      v-else
      :to="detailUrl"
      class="bg-muted text-muted-foreground relative flex aspect-3/2 h-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border text-xs font-medium transition hover:opacity-80"
    >
      {{ initials }}
    </NuxtLink>

    <Input
      v-if="canEdit"
      ref="editInputEl"
      :model-value="isEditing ? editName : partner.partner_name"
      :readonly="!isEditing"
      class="h-8 min-w-0 grow rounded-md border px-2 py-0 text-sm tracking-tight transition-[color,background-color,border-color,box-shadow] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none"
      :class="
        isEditing
          ? 'border-ring ring-ring bg-background dark:bg-background shadow-xs ring-[1px]'
          : 'border-transparent bg-transparent dark:bg-transparent shadow-none cursor-pointer'
      "
      @update:model-value="editName = $event"
      @click="handleNameClick"
      @blur="isEditing && saveName()"
      @keydown.enter.prevent="isEditing && saveName()"
      @keydown.escape="isEditing && cancelEdit()"
    />
    <NuxtLink
      v-else
      :to="detailUrl"
      class="min-w-0 truncate tracking-tight transition hover:opacity-80"
    >
      {{ partner.partner_name }}
    </NuxtLink>
  </div>
</template>

<script setup>
import { Input } from "@/components/ui/input";
import { Lightbox } from "@/components/ui/lightbox";
import { toast } from "vue-sonner";

const props = defineProps({
  partner: { type: Object, required: true },
  baseUrl: { type: String, required: true },
  linkSuffix: { type: String, default: "" },
});

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canEdit = computed(() => hasPermission("partners.update"));

const detailUrl = computed(
  () => `${props.baseUrl}/${props.partner.partner_slug}${props.linkSuffix}`
);

const logoUrl = computed(
  () =>
    props.partner.partner_logo?.sm ||
    props.partner.partner_logo?.url ||
    props.partner.partner_logo?.original
);

const logoItems = computed(() => {
  const logo = props.partner.partner_logo;
  if (!logo) {
    return [];
  }
  return [
    {
      ...logo,
      name: props.partner.partner_name,
      alt: logo.alt || props.partner.partner_name,
    },
  ];
});

const initials = computed(() => {
  const names = (props.partner.partner_name || "").trim().split(" ");
  const first = names[0]?.[0]?.toUpperCase() || "";
  const last =
    names.length === 1
      ? names[0]?.[1]?.toUpperCase() || ""
      : names[names.length - 1]?.[0]?.toUpperCase() || "";
  return first + last;
});

// Inline edit state
const isEditing = ref(false);
const editName = ref("");
const editInputEl = ref(null);

const focusInput = () => {
  const el = editInputEl.value?.$el ?? editInputEl.value;
  el?.focus?.();
  el?.select?.();
};

const handleNameClick = () => {
  if (!isEditing.value) {
    startEditing();
  }
};

const startEditing = () => {
  editName.value = props.partner.partner_name;
  isEditing.value = true;
  nextTick(focusInput);
};

const cancelEdit = () => {
  isEditing.value = false;
  editName.value = "";
};

const saveName = async () => {
  const newName = editName.value.trim();
  if (!newName || newName === props.partner.partner_name) {
    cancelEdit();
    return;
  }

  const oldName = props.partner.partner_name;
  props.partner.partner_name = newName;
  isEditing.value = false;

  try {
    await client(`/api/partners/${props.partner.partner_slug}`, {
      method: "PUT",
      body: { name: newName },
    });
    toast.success("Partner updated");
  } catch (err) {
    props.partner.partner_name = oldName;
    toast.error("Failed to update partner", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  }
};
</script>
