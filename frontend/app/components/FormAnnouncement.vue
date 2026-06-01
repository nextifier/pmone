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
            <InputErrorMessage :errors="errors.title" />
          </div>

          <div class="space-y-2">
            <Label for="description">Description</Label>
            <Textarea id="description" v-model="form.description" rows="4" />
            <InputErrorMessage :errors="errors.description" />
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
              <InputErrorMessage :errors="errors.type" />
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
              <InputErrorMessage :errors="errors.status" />
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
            <InputErrorMessage :errors="errors.tmp_image" />
          </div>

          <div class="space-y-2">
            <Label for="icon">Icon</Label>
            <IconPicker v-model="form.icon" placeholder="Pick an icon" />
            <InputErrorMessage :errors="errors.icon" />
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
            <InputErrorMessage :errors="errors.start_time" />
          </div>

          <div class="space-y-2">
            <Label for="end_time">End</Label>
            <DatePicker
              with-time
              v-model="form.end_time"
              placeholder="Select end time"
              :default-hour="23"
            />
            <InputErrorMessage :errors="errors.end_time" />
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
              <MultiSelect
                v-model="selectedRoles"
                :options="roleOptions"
                placeholder="Pick one or more roles"
                open-on-focus
              />
              <p class="text-muted-foreground text-xs">
                User must hold any of these roles to see the announcement.
              </p>
              <InputErrorMessage :errors="errors.target_roles" />
            </div>

            <div class="space-y-2">
              <Label>Specific users</Label>
              <MultiSelect
                v-model="selectedUsers"
                :options="userOptions"
                placeholder="Pick users to target"
                open-on-focus
              />
              <p class="text-muted-foreground text-xs">
                Optional. Adds these users on top of role targeting.
              </p>
              <InputErrorMessage :errors="errors.target_user_ids" />
            </div>

            <div class="space-y-2">
              <Label>Specific events</Label>
              <MultiSelect
                v-model="selectedEvents"
                :options="eventOptions"
                placeholder="Pick events to target"
                open-on-focus
              />
              <p class="text-muted-foreground text-xs">
                Optional. Members of the selected events' projects will see it.
              </p>
              <InputErrorMessage :errors="errors.target_event_ids" />
            </div>

            <div class="space-y-2">
              <Label>Specific projects</Label>
              <MultiSelect
                v-model="selectedProjects"
                :options="projectOptions"
                placeholder="Pick projects to target"
                open-on-focus
              />
              <p class="text-muted-foreground text-xs">
                Optional. Members of these projects will see the announcement.
              </p>
              <InputErrorMessage :errors="errors.target_project_ids" />
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
            <Input
              id="order_column"
              v-model.number="form.order_column"
              type="number"
              min="0"
              placeholder="0"
            />
            <p class="text-muted-foreground text-xs">
              Lower numbers appear first.
            </p>
            <InputErrorMessage :errors="errors.order_column" />
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
import { IconPicker } from "@/components/ui/icon-picker";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { MultiSelect } from "@/components/ui/multi-select";
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
