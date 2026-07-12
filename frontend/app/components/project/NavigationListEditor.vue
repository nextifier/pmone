<template>
  <div class="space-y-3">
    <div class="flex items-start justify-between gap-4">
      <div class="space-y-1">
        <Label class="text-sm font-medium tracking-tight">{{ title }}</Label>
        <p v-if="description" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          {{ description }}
        </p>
      </div>
      <Button variant="outline" size="sm" type="button" class="shrink-0" @click="openCreateDialog">
        <Icon name="hugeicons:add-01" class="-ml-1 size-4 shrink-0" />
        Add item
      </Button>
    </div>

    <div
      v-if="!localItems.length"
      class="text-muted-foreground rounded-md border border-dashed py-8 text-center text-sm tracking-tight"
    >
      No items yet.
    </div>

    <div v-else ref="listContainer" class="space-y-2">
      <div
        v-for="item in localItems"
        :key="item._key"
        class="bg-card flex items-center gap-x-3 rounded-xl border px-3 py-3"
      >
        <Icon
          name="lucide:grip-vertical"
          class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab"
        />

        <div
          class="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg"
        >
          <Icon :name="isGroup(item) ? 'hugeicons:folder-01' : 'hugeicons:link-01'" class="size-4" />
        </div>

        <div class="min-w-0 flex-1">
          <span class="truncate text-sm font-medium tracking-tight">{{ item.label || "Untitled" }}</span>
          <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
            {{
              isGroup(item)
                ? `${item.links.length} link${item.links.length === 1 ? "" : "s"}`
                : item.path || "No path set"
            }}
          </p>
        </div>

        <div class="flex shrink-0 items-center gap-1">
          <Button
            variant="ghost"
            size="iconSm"
            type="button"
            v-tippy="'Edit'"
            @click="openEditDialog(item)"
          >
            <Icon name="hugeicons:edit-02" class="size-4" />
          </Button>
          <Button
            variant="ghost"
            size="iconSm"
            type="button"
            class="hover:bg-destructive/10 text-destructive"
            v-tippy="'Remove'"
            @click="confirmDelete(item)"
          >
            <Icon name="hugeicons:delete-02" class="size-4" />
          </Button>
        </div>
      </div>
    </div>

    <!-- Create / Edit dialog -->
    <DialogResponsive v-model:open="dialogOpen" dialog-max-width="32rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tighter">
            {{ editing ? "Edit item" : "Add item" }}
          </h3>

          <form class="mt-4 space-y-4" @submit.prevent="handleSubmit">
            <div class="space-y-2">
              <Label>Type</Label>
              <Tabs v-model="form.type" variant="segmented">
                <TabsList>
                  <TabsIndicator />
                  <TabsTrigger value="link">Link</TabsTrigger>
                  <TabsTrigger value="group">Group</TabsTrigger>
                </TabsList>
              </Tabs>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                A group renders as a dropdown of links sharing one label.
              </p>
            </div>

            <div class="space-y-2">
              <Label for="nav-item-label">Label</Label>
              <Input id="nav-item-label" v-model="form.label" required placeholder="e.g. Tickets" />
              <FieldError :errors="errors.label" />
            </div>

            <div v-if="form.type === 'link'" class="space-y-2">
              <Label for="nav-item-path">Path</Label>
              <Input id="nav-item-path" v-model="form.path" required placeholder="/tickets" />
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Internal page (<code>/tickets</code>), anchor (<code>#agenda</code>), or full URL
                (<code>https://...</code>).
              </p>
              <FieldError :errors="errors.path" />
            </div>

            <div v-else class="space-y-2">
              <Label>Links</Label>
              <div class="space-y-2">
                <div
                  v-for="(link, index) in form.links"
                  :key="link._key"
                  class="flex items-start gap-x-2"
                >
                  <div class="grid flex-1 grid-cols-1 gap-x-2 gap-y-2 sm:grid-cols-2">
                    <Input v-model="link.label" placeholder="Label" />
                    <Input v-model="link.path" placeholder="/path, #anchor, or https://..." />
                  </div>
                  <Button
                    variant="ghost"
                    size="iconSm"
                    type="button"
                    class="hover:bg-destructive/10 text-destructive shrink-0"
                    v-tippy="'Remove link'"
                    @click="removeLink(index)"
                  >
                    <Icon name="hugeicons:delete-02" class="size-4" />
                  </Button>
                </div>
              </div>
              <Button variant="outline" size="sm" type="button" @click="addLink">
                <Icon name="hugeicons:add-01" class="-ml-1 size-4 shrink-0" />
                Add link
              </Button>
              <FieldError :errors="errors.links" />
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <Button variant="outline" type="button" @click="dialogOpen = false">Cancel</Button>
              <Button type="submit">{{ editing ? "Save changes" : "Add item" }}</Button>
            </div>
          </form>
        </div>
      </template>
    </DialogResponsive>

    <!-- Delete confirmation -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tighter">Remove item?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            "{{ deletingItem?.label || "This item" }}" will be removed from {{ title }} navigation.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <Button variant="outline" type="button" @click="deleteDialogOpen = false">Cancel</Button>
            <Button variant="destructive" type="button" @click="handleDelete">Remove</Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Input } from "@/components/ui/input";
import { FieldError } from "@/components/ui/field";
import { Label } from "@/components/ui/label";
import { Tabs, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useSortableList } from "@/composables/useSortableList";
import { nextTick, reactive, ref, watch } from "vue";
import { toast } from "vue-sonner";

defineProps({
  title: { type: String, required: true },
  description: { type: String, default: "" },
});

// Bound to the parent's `form.site_config.nav.{header,dialog,footer}` array.
// Items carry a client-only `_key` (for :key / drag tracking) that is stripped
// before the parent builds the save payload (see stripNavKeys in the page).
const model = defineModel({ type: Array, default: () => [] });

/**
 * Local writable working copy. SortableJS (via useSortableList) mutates its
 * target array in place, and add/edit/delete below splice too - but `model`
 * proxies a readonly prop, so mutating it directly is blocked by Vue (same
 * constraint documented in GalleryManager.vue). Mutate this copy, then
 * `commit()` the result back to the model as a whole-array assignment.
 */
const localItems = ref([]);
let suppressResync = false;

watch(
  model,
  (val) => {
    if (suppressResync) return;
    localItems.value = Array.isArray(val) ? val.map((item) => ({ ...item })) : [];
  },
  { immediate: true, deep: true }
);

function commit() {
  suppressResync = true;
  model.value = localItems.value.map((item) => ({ ...item }));
  nextTick(() => {
    suppressResync = false;
  });
}

let keySeed = 0;
const nextKey = () => `nav-${Date.now()}-${keySeed++}`;

const isGroup = (item) => Array.isArray(item.links);

const dialogOpen = ref(false);
const editing = ref(null);
const errors = ref({});

const emptyForm = () => ({
  type: "link",
  label: "",
  path: "",
  links: [],
});

const form = reactive(emptyForm());

const addLink = () => form.links.push({ _key: nextKey(), label: "", path: "" });
const removeLink = (index) => form.links.splice(index, 1);

const openCreateDialog = () => {
  editing.value = null;
  errors.value = {};
  Object.assign(form, emptyForm());
  dialogOpen.value = true;
};

const openEditDialog = (item) => {
  editing.value = item;
  errors.value = {};
  if (isGroup(item)) {
    Object.assign(form, {
      type: "group",
      label: item.label,
      path: "",
      links: item.links.map((l) => ({ _key: l._key || nextKey(), label: l.label, path: l.path })),
    });
  } else {
    Object.assign(form, {
      type: "link",
      label: item.label,
      path: item.path,
      links: [],
    });
  }
  dialogOpen.value = true;
};

const NAV_PATH_PATTERN = /^(\/|#|https?:\/\/)/;

const handleSubmit = () => {
  errors.value = {};

  const label = String(form.label ?? "").trim();
  if (!label) {
    errors.value.label = ["Label is required."];
    return;
  }

  if (form.type === "group") {
    const cleanLinks = form.links
      .map((l) => ({ _key: l._key, label: String(l.label ?? "").trim(), path: String(l.path ?? "").trim() }))
      .filter((l) => l.label || l.path);

    if (!cleanLinks.length) {
      errors.value.links = ["Add at least one link."];
      return;
    }

    const invalid = cleanLinks.find((l) => !l.label || !NAV_PATH_PATTERN.test(l.path));
    if (invalid) {
      errors.value.links = ["Every link needs a label and a valid path (/, #, or https://)."];
      return;
    }

    saveItem({ _key: editing.value?._key || nextKey(), label, links: cleanLinks });
  } else {
    const path = String(form.path ?? "").trim();
    if (!NAV_PATH_PATTERN.test(path)) {
      errors.value.path = ["Path must start with /, #, or http(s)://."];
      return;
    }

    saveItem({ _key: editing.value?._key || nextKey(), label, path });
  }
};

function saveItem(entry) {
  if (editing.value) {
    const index = localItems.value.findIndex((i) => i._key === editing.value._key);
    if (index !== -1) localItems.value.splice(index, 1, entry);
  } else {
    localItems.value.push(entry);
  }
  commit();
  dialogOpen.value = false;
  toast.success(editing.value ? "Item updated" : "Item added");
}

const deleteDialogOpen = ref(false);
const deletingItem = ref(null);

const confirmDelete = (item) => {
  deletingItem.value = item;
  deleteDialogOpen.value = true;
};

const handleDelete = () => {
  if (!deletingItem.value) return;
  const index = localItems.value.findIndex((i) => i._key === deletingItem.value._key);
  if (index !== -1) localItems.value.splice(index, 1);
  commit();
  deleteDialogOpen.value = false;
  toast.success("Item removed");
};

// --- Drag reorder (client-side only; the parent form auto-saves the whole
// site_config.nav object on the next deep-watch tick, see website-settings.vue) ---
const listContainer = ref(null);
useSortableList(listContainer, localItems, { onReorder: commit });
</script>
