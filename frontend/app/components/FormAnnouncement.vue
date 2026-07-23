<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-8">
    <!-- Basic info -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Basic info</div>
        <div class="frame-description">Title, description, type, and status.</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label for="title">Title</Label>
            <Input id="title" v-model="form.title" type="text" required maxlength="255" />
            <FieldError :errors="errors.title" />
          </div>

          <div class="space-y-2">
            <Label for="description">Description</Label>
            <Textarea id="description" v-model="form.description" rows="4" />
            <FieldError :errors="errors.description" />
          </div>

          <div class="grid grid-cols-2 gap-x-2 gap-y-6">
            <div class="space-y-2">
              <Label for="type">Type</Label>
              <Select v-model="form.type">
                <SelectTrigger class="w-full">
                  <SelectValue placeholder="Select type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="info">Info</SelectItem>
                  <SelectItem value="success">Success</SelectItem>
                  <SelectItem value="warning">Warning</SelectItem>
                  <SelectItem value="error">Error</SelectItem>
                  <SelectItem value="marketing">Marketing</SelectItem>
                </SelectContent>
              </Select>
              <FieldError :errors="errors.type" />
            </div>

            <div class="space-y-2">
              <Label for="status">Status</Label>
              <Select v-model="form.status">
                <SelectTrigger class="w-full">
                  <SelectValue placeholder="Select status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="draft">Draft</SelectItem>
                  <SelectItem value="published">Published</SelectItem>
                  <SelectItem value="archived">Archived</SelectItem>
                </SelectContent>
              </Select>
              <FieldError :errors="errors.status" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Visual -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Visual</div>
        <div class="frame-description">
          Pick an image or just an icon. Image takes priority if both are set.
        </div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label>Image</Label>
            <InputFileImage
              ref="imageInputRef"
              v-model="imageFiles"
              :initial-image="initialData?.image"
              v-model:delete-flag="deleteImage"
              container-class="relative isolate aspect-video max-w-full"
            />
            <FieldError :errors="errors.tmp_image" />
          </div>

          <div class="space-y-2">
            <Label for="icon">Icon</Label>
            <IconPicker v-model="form.icon" placeholder="Pick an icon" />
            <FieldError :errors="errors.icon" />
          </div>
        </div>
      </div>
    </div>

    <!-- Schedule -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Schedule</div>
        <div class="frame-description">
          Optionally limit when this announcement appears. Leave blank for always-on.
        </div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-2 gap-x-2 gap-y-6">
          <div class="space-y-2">
            <Label for="start_time">Start</Label>
            <DatePicker
              with-time
              v-model="form.start_time"
              placeholder="Select start time"
              :default-hour="8"
            />
            <FieldError :errors="errors.start_time" />
          </div>

          <div class="space-y-2">
            <Label for="end_time">End</Label>
            <DatePicker
              with-time
              v-model="form.end_time"
              placeholder="Select end time"
              :default-hour="23"
            />
            <FieldError :errors="errors.end_time" />
          </div>
        </div>
      </div>
    </div>

    <!-- Targeting -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Targeting</div>
        <div class="frame-description">
          Choose who sees this announcement. Toggle global to show to everyone.
        </div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="flex items-center justify-between gap-4">
            <div>
              <Label for="is_global">Show to all users</Label>
              <p class="text-muted-foreground mt-0.5 text-xs">
                When enabled, role and other targeting filters are bypassed.
              </p>
            </div>
            <Switch id="is_global" v-model="form.is_global" />
          </div>

          <template v-if="!form.is_global">
            <div class="space-y-2">
              <Label>Roles</Label>
              <Combobox v-model="selectedRoles" multiple ignore-filter open-on-focus>
                <ComboboxAnchor class="w-full">
                  <ComboboxChips
                    v-model="selectedRoles"
                    :display-value="(option) => option.label"
                    class="w-full"
                  >
                    <ComboboxChip
                      v-for="item in selectedRoles"
                      :key="item.value"
                      :value="item"
                    />
                    <ComboboxChipsInput
                      v-model="roleQuery"
                      placeholder="Pick one or more roles"
                    />
                    <button
                      v-if="selectedRoles.length"
                      type="button"
                      class="text-muted-foreground/80 hover:text-foreground focus-visible:ring-ring/50 ml-auto flex size-5 shrink-0 cursor-pointer items-center justify-center rounded-sm outline-none focus-visible:ring-2"
                      aria-label="Clear all"
                      @click="selectedRoles = []"
                    >
                      <Icon name="lucide:x" class="size-3.5" />
                    </button>
                  </ComboboxChips>
                </ComboboxAnchor>
                <ComboboxList class="w-(--reka-combobox-trigger-width)">
                  <ComboboxViewport>
                    <ComboboxEmpty>No roles found.</ComboboxEmpty>
                    <ComboboxGroup v-if="filteredRoles.length">
                      <ComboboxItem
                        v-for="opt in filteredRoles"
                        :key="opt.value"
                        :value="opt"
                      >
                        {{ opt.label }}
                        <ComboboxItemIndicator>
                          <Icon name="lucide:check" class="ml-auto size-4" />
                        </ComboboxItemIndicator>
                      </ComboboxItem>
                    </ComboboxGroup>
                  </ComboboxViewport>
                </ComboboxList>
              </Combobox>
              <p class="text-muted-foreground text-xs">
                User must hold any of these roles to see the announcement.
              </p>
              <FieldError :errors="errors.target_roles" />
            </div>

            <div class="space-y-2">
              <Label>Specific users</Label>
              <Combobox v-model="selectedUsers" multiple ignore-filter open-on-focus>
                <ComboboxAnchor class="w-full">
                  <ComboboxChips
                    v-model="selectedUsers"
                    :display-value="(option) => option.label"
                    class="w-full"
                  >
                    <ComboboxChip
                      v-for="item in selectedUsers"
                      :key="item.value"
                      :value="item"
                    />
                    <ComboboxChipsInput
                      v-model="userQuery"
                      placeholder="Pick users to target"
                    />
                    <button
                      v-if="selectedUsers.length"
                      type="button"
                      class="text-muted-foreground/80 hover:text-foreground focus-visible:ring-ring/50 ml-auto flex size-5 shrink-0 cursor-pointer items-center justify-center rounded-sm outline-none focus-visible:ring-2"
                      aria-label="Clear all"
                      @click="selectedUsers = []"
                    >
                      <Icon name="lucide:x" class="size-3.5" />
                    </button>
                  </ComboboxChips>
                </ComboboxAnchor>
                <ComboboxList class="w-(--reka-combobox-trigger-width)">
                  <ComboboxViewport>
                    <ComboboxEmpty>No users found.</ComboboxEmpty>
                    <ComboboxGroup v-if="filteredUsers.length">
                      <ComboboxItem
                        v-for="opt in filteredUsers"
                        :key="opt.value"
                        :value="opt"
                      >
                        {{ opt.label }}
                        <ComboboxItemIndicator>
                          <Icon name="lucide:check" class="ml-auto size-4" />
                        </ComboboxItemIndicator>
                      </ComboboxItem>
                    </ComboboxGroup>
                  </ComboboxViewport>
                </ComboboxList>
              </Combobox>
              <p class="text-muted-foreground text-xs">
                Optional. Adds these users on top of role targeting.
              </p>
              <FieldError :errors="errors.target_user_ids" />
            </div>

            <div class="space-y-2">
              <Label>Specific events</Label>
              <Combobox v-model="selectedEvents" multiple ignore-filter open-on-focus>
                <ComboboxAnchor class="w-full">
                  <ComboboxChips
                    v-model="selectedEvents"
                    :display-value="(option) => option.label"
                    class="w-full"
                  >
                    <ComboboxChip
                      v-for="item in selectedEvents"
                      :key="item.value"
                      :value="item"
                    />
                    <ComboboxChipsInput
                      v-model="eventQuery"
                      placeholder="Pick events to target"
                    />
                    <button
                      v-if="selectedEvents.length"
                      type="button"
                      class="text-muted-foreground/80 hover:text-foreground focus-visible:ring-ring/50 ml-auto flex size-5 shrink-0 cursor-pointer items-center justify-center rounded-sm outline-none focus-visible:ring-2"
                      aria-label="Clear all"
                      @click="selectedEvents = []"
                    >
                      <Icon name="lucide:x" class="size-3.5" />
                    </button>
                  </ComboboxChips>
                </ComboboxAnchor>
                <ComboboxList class="w-(--reka-combobox-trigger-width)">
                  <ComboboxViewport>
                    <ComboboxEmpty>No events found.</ComboboxEmpty>
                    <ComboboxGroup v-if="filteredEvents.length">
                      <ComboboxItem
                        v-for="opt in filteredEvents"
                        :key="opt.value"
                        :value="opt"
                      >
                        {{ opt.label }}
                        <ComboboxItemIndicator>
                          <Icon name="lucide:check" class="ml-auto size-4" />
                        </ComboboxItemIndicator>
                      </ComboboxItem>
                    </ComboboxGroup>
                  </ComboboxViewport>
                </ComboboxList>
              </Combobox>
              <p class="text-muted-foreground text-xs">
                Optional. Members of the selected events' projects will see it.
              </p>
              <FieldError :errors="errors.target_event_ids" />
            </div>

            <div class="space-y-2">
              <Label>Specific projects</Label>
              <Combobox v-model="selectedProjects" multiple ignore-filter open-on-focus>
                <ComboboxAnchor class="w-full">
                  <ComboboxChips
                    v-model="selectedProjects"
                    :display-value="(option) => option.label"
                    class="w-full"
                  >
                    <ComboboxChip
                      v-for="item in selectedProjects"
                      :key="item.value"
                      :value="item"
                    />
                    <ComboboxChipsInput
                      v-model="projectQuery"
                      placeholder="Pick projects to target"
                    />
                    <button
                      v-if="selectedProjects.length"
                      type="button"
                      class="text-muted-foreground/80 hover:text-foreground focus-visible:ring-ring/50 ml-auto flex size-5 shrink-0 cursor-pointer items-center justify-center rounded-sm outline-none focus-visible:ring-2"
                      aria-label="Clear all"
                      @click="selectedProjects = []"
                    >
                      <Icon name="lucide:x" class="size-3.5" />
                    </button>
                  </ComboboxChips>
                </ComboboxAnchor>
                <ComboboxList class="w-(--reka-combobox-trigger-width)">
                  <ComboboxViewport>
                    <ComboboxEmpty>No projects found.</ComboboxEmpty>
                    <ComboboxGroup v-if="filteredProjects.length">
                      <ComboboxItem
                        v-for="opt in filteredProjects"
                        :key="opt.value"
                        :value="opt"
                      >
                        {{ opt.label }}
                        <ComboboxItemIndicator>
                          <Icon name="lucide:check" class="ml-auto size-4" />
                        </ComboboxItemIndicator>
                      </ComboboxItem>
                    </ComboboxGroup>
                  </ComboboxViewport>
                </ComboboxList>
              </Combobox>
              <p class="text-muted-foreground text-xs">
                Optional. Members of these projects will see the announcement.
              </p>
              <FieldError :errors="errors.target_project_ids" />
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Calls to action -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Calls to action</div>
        <div class="frame-description">
          Add buttons or inline links shown alongside the announcement.
        </div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-3">
          <div v-if="form.cta_actions.length === 0" class="text-muted-foreground text-sm tracking-tight">
            No CTAs yet.
          </div>

          <div
            v-for="(cta, idx) in form.cta_actions"
            :key="idx"
            class="bg-muted/30 space-y-4 rounded-lg border p-4"
          >
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium tracking-tight">CTA {{ idx + 1 }}</span>
              <Button
                type="button"
                variant="ghost"
                size="iconSm"
                class="text-muted-foreground hover:text-destructive"
                v-tippy="'Remove CTA'"
                @click="removeCta(idx)"
              >
                <Icon name="hugeicons:delete-01" class="size-4" />
              </Button>
            </div>

            <div class="grid grid-cols-1 gap-x-2 gap-y-6 sm:grid-cols-2">
              <div class="space-y-2">
                <Label :for="`cta-label-${idx}`">Label</Label>
                <Input
                  :id="`cta-label-${idx}`"
                  v-model="cta.label"
                  type="text"
                  required
                  maxlength="100"
                />
              </div>
              <div class="space-y-2">
                <Label :for="`cta-url-${idx}`">URL</Label>
                <Input
                  :id="`cta-url-${idx}`"
                  v-model="cta.url"
                  type="text"
                  required
                  maxlength="500"
                />
              </div>
              <div class="space-y-2">
                <Label :for="`cta-style-${idx}`">Style</Label>
                <Select v-model="cta.style">
                  <SelectTrigger class="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="link">Link</SelectItem>
                    <SelectItem value="button-primary">Primary button</SelectItem>
                    <SelectItem value="button-outline">Outline button</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label :for="`cta-icon-${idx}`">Icon (optional)</Label>
                <IconPicker v-model="cta.icon" placeholder="Pick an icon" />
              </div>
            </div>
          </div>

          <Button
            type="button"
            variant="outline"
            size="sm"
            class="w-full border-dashed"
            @click="addCta"
          >
            <Icon name="hugeicons:add-01" class="size-4" />
            Add CTA
          </Button>
        </div>
      </div>
    </div>

    <!-- Advanced -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Advanced</div>
        <div class="frame-description">Behavior and ordering.</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="flex items-center justify-between gap-4">
            <div>
              <Label for="is_dismissible">Allow dismiss</Label>
              <p class="text-muted-foreground mt-0.5 text-xs">
                Users can close this announcement permanently.
              </p>
            </div>
            <Switch id="is_dismissible" v-model="form.is_dismissible" />
          </div>

          <div class="space-y-2">
            <Label for="order_column">Order</Label>
            <InputNumber
              id="order_column"
              v-model="form.order_column"
              :min="0"
              placeholder="0"
            />
            <p class="text-muted-foreground text-xs">
              Lower numbers appear first.
            </p>
            <FieldError :errors="errors.order_column" />
          </div>
        </div>
      </div>
    </div>

    <div class="flex justify-end">
      <Button type="submit" size="sm" :disabled="loading">
        <Spinner v-if="loading" />
        {{ loading ? submitLoadingText : submitText }}
        <KbdGroup>
          <Kbd>{{ metaSymbol }}</Kbd>
          <Kbd>S</Kbd>
        </KbdGroup>
      </Button>
    </div>
  </form>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import {
  Combobox,
  ComboboxAnchor,
  ComboboxChip,
  ComboboxChips,
  ComboboxChipsInput,
  ComboboxEmpty,
  ComboboxGroup,
  ComboboxItem,
  ComboboxItemIndicator,
  ComboboxList,
  ComboboxViewport,
} from "@/components/ui/combobox";
import { IconPicker } from "@/components/ui/icon-picker";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useFilter } from "reka-ui";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";

const props = defineProps({
  initialData: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
  isCreate: { type: Boolean, default: false },
  submitText: { type: String, default: "Save Announcement" },
  submitLoadingText: { type: String, default: "Saving.." },
});

const emit = defineEmits(["submit", "cancel"]);

const { metaSymbol } = useShortcuts();
const client = useSanctumClient();

function createEmptyForm() {
  return {
    title: "",
    description: "",
    icon: "",
    type: "info",
    status: "draft",
    is_global: false,
    is_dismissible: true,
    start_time: null,
    end_time: null,
    order_column: 0,
    cta_actions: [],
  };
}

const form = reactive(createEmptyForm());

const imageFiles = ref([]);
const deleteImage = ref(false);
const imageInputRef = ref(null);

const selectedRoles = ref([]);
const selectedUsers = ref([]);
const selectedEvents = ref([]);
const selectedProjects = ref([]);

const roleOptions = ref([]);
const userOptions = ref([]);
const eventOptions = ref([]);
const projectOptions = ref([]);

// The target pickers are comboboxes in `multiple` mode. `ignore-filter` hands filtering
// to us, so a picked option keeps its place in the list with a check instead of
// disappearing under the cursor.
const { contains } = useFilter({ sensitivity: "base" });

const roleQuery = ref("");
const userQuery = ref("");
const eventQuery = ref("");
const projectQuery = ref("");

const matching = (options, query) =>
  options.filter((o) => contains(o.label, query) || contains(o.value, query));

const filteredRoles = computed(() => matching(roleOptions.value, roleQuery.value));
const filteredUsers = computed(() => matching(userOptions.value, userQuery.value));
const filteredEvents = computed(() => matching(eventOptions.value, eventQuery.value));
const filteredProjects = computed(() => matching(projectOptions.value, projectQuery.value));

// reka only auto-clears the search text when the selection changed without typing, so
// picking a filtered option would otherwise leave the query in the field.
watch(selectedRoles, () => (roleQuery.value = ""));
watch(selectedUsers, () => (userQuery.value = ""));
watch(selectedEvents, () => (eventQuery.value = ""));
watch(selectedProjects, () => (projectQuery.value = ""));

async function loadRoles() {
  try {
    const response = await client("/api/users/roles");
    const list = response?.data || response?.roles || [];
    roleOptions.value = list.map((r) => ({
      label: r.label || r.name,
      value: r.name,
    }));
  } catch {
    roleOptions.value = [];
  }
}

async function loadUsers() {
  try {
    const response = await client("/api/users?per_page=100");
    const list = response?.data || [];
    userOptions.value = list.map((u) => ({
      label: u.name + (u.email ? ` (${u.email})` : ""),
      value: String(u.id),
    }));
  } catch {
    userOptions.value = [];
  }
}

async function loadEvents() {
  try {
    const response = await client("/api/events?per_page=100");
    const list = response?.data || [];
    eventOptions.value = list.map((e) => ({
      label: e.title || e.name,
      value: String(e.id),
    }));
  } catch {
    eventOptions.value = [];
  }
}

async function loadProjects() {
  try {
    const response = await client("/api/projects?per_page=100");
    const list = response?.data || [];
    projectOptions.value = list.map((p) => ({
      label: p.name,
      value: String(p.id),
    }));
  } catch {
    projectOptions.value = [];
  }
}

onMounted(() => {
  loadRoles();
  loadUsers();
  loadEvents();
  loadProjects();
});

function populateForm(data) {
  if (!data || Object.keys(data).length === 0) return;

  form.title = data.title || "";
  form.description = data.description || "";
  form.icon = data.icon || "";
  form.type = data.type || "info";
  form.status = data.status || "draft";
  form.is_global = !!data.is_global;
  form.is_dismissible = data.is_dismissible !== false;
  form.start_time = data.start_time ? new Date(data.start_time) : null;
  form.end_time = data.end_time ? new Date(data.end_time) : null;
  form.order_column = data.order_column ?? 0;
  form.cta_actions = Array.isArray(data.cta_actions)
    ? data.cta_actions.map((c) => ({
        label: c.label || "",
        url: c.url || "",
        style: c.style || "link",
        icon: c.icon || "",
      }))
    : [];

  selectedRoles.value = (data.target_roles || []).map((name) => ({
    label: name,
    value: name,
  }));
  selectedUsers.value = (data.target_users || []).map((u) => ({
    label: u.name + (u.email ? ` (${u.email})` : ""),
    value: String(u.id),
  }));
  selectedEvents.value = (data.target_events || []).map((e) => ({
    label: e.title || e.name,
    value: String(e.id),
  }));
  selectedProjects.value = (data.target_projects || []).map((p) => ({
    label: p.name,
    value: String(p.id),
  }));

  imageFiles.value = [];
  deleteImage.value = false;
}

watch(() => props.initialData, populateForm, { immediate: true });

function addCta() {
  form.cta_actions.push({ label: "", url: "", style: "link", icon: "" });
}

function removeCta(idx) {
  form.cta_actions.splice(idx, 1);
}

function formatDateTimeForBackend(date) {
  if (!date) return null;
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  const h = String(date.getHours()).padStart(2, "0");
  const mi = String(date.getMinutes()).padStart(2, "0");
  return `${y}-${m}-${d} ${h}:${mi}:00`;
}

function handleSubmit() {
  const payload = {
    title: form.title,
    description: form.description || null,
    icon: form.icon || null,
    type: form.type,
    status: form.status,
    is_global: form.is_global,
    is_dismissible: form.is_dismissible,
    order_column: Number(form.order_column) || 0,
    start_time: formatDateTimeForBackend(form.start_time),
    end_time: formatDateTimeForBackend(form.end_time),
    cta_actions: form.cta_actions
      .filter((c) => c.label && c.url)
      .map((c) => ({
        label: c.label,
        url: c.url,
        style: c.style,
        icon: c.icon || null,
      })),
    target_roles: form.is_global ? [] : selectedRoles.value.map((r) => r.value),
    target_user_ids: form.is_global
      ? []
      : selectedUsers.value.map((u) => Number(u.value)).filter(Boolean),
    target_event_ids: form.is_global
      ? []
      : selectedEvents.value.map((e) => Number(e.value)).filter(Boolean),
    target_project_ids: form.is_global
      ? []
      : selectedProjects.value.map((p) => Number(p.value)).filter(Boolean),
  };

  const tmpImage = imageFiles.value?.[0];
  if (tmpImage && typeof tmpImage === "string" && tmpImage.startsWith("tmp-")) {
    payload.tmp_image = tmpImage;
  } else if (deleteImage.value && !tmpImage) {
    payload.delete_image = true;
  }

  emit("submit", payload);
}

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      handleSubmit();
    },
  },
});
</script>
