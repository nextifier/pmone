<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-8" autocomplete="off">
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Personal Information</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <Field :data-invalid="!!errors?.name">
            <FieldLabel for="name">Full Name</FieldLabel>
            <Input id="name" v-model="form.name" type="text" autocomplete="one-time-code" :aria-invalid="!!errors?.name" />
            <p class="text-muted-foreground line-clamp-1 text-xs tracking-tight">
              {{ isCreate ? "Will be auto-generated from email if left empty." : "" }}
            </p>
            <FieldError :errors="errors.name" />
          </Field>

          <Field :data-invalid="!!errors?.username">
            <FieldLabel for="username">Username</FieldLabel>
            <Input
              :aria-invalid="!!errors?.username"
              id="username"
              v-model="form.username"
              type="text"
              :required="!isCreate"
              autocomplete="one-time-code"
            />
            <p class="text-muted-foreground line-clamp-1 text-xs tracking-tight">
              {{ isCreate ? "Will be auto-generated if left empty." : "" }}
            </p>
            <FieldError :errors="errors.username" />
          </Field>

          <Field :data-invalid="!!errors?.email">
            <FieldLabel for="email">Email Address</FieldLabel>
            <Input
              :aria-invalid="!!errors?.email"
              id="email"
              v-model="form.email"
              type="email"
              required
              autocomplete="one-time-code"
            />
            <FieldError :errors="errors.email" />
          </Field>

          <Field :data-invalid="!!errors?.phone">
            <FieldLabel for="phone">Phone Number</FieldLabel>
            <InputPhone v-model="form.phone" id="phone" :aria-invalid="!!errors?.phone" />
            <FieldError :errors="errors.phone" />
          </Field>

          <Field :data-invalid="!!errors?.title">
            <FieldLabel for="title">Job Title</FieldLabel>
            <Input id="title" v-model="form.title" type="text" autocomplete="one-time-code" :aria-invalid="!!errors?.title" />
            <FieldError :errors="errors.title" />
          </Field>

          <Field :data-invalid="!!errors?.gender">
            <FieldLabel for="gender">Gender</FieldLabel>

            <RadioGroup class="flex flex-wrap gap-2" v-model="form.gender">
              <div
                class="border-border has-data-[state=checked]:border-primary/50 relative flex flex-col items-start gap-4 rounded-md border p-2 shadow-xs outline-none"
              >
                <div class="flex items-center gap-2">
                  <RadioGroupItem id="male" value="male" class="after:absolute after:inset-0" />
                  <Label for="male">Male</Label>
                </div>
              </div>

              <div
                class="border-border has-data-[state=checked]:border-primary/50 relative flex flex-col items-start gap-4 rounded-md border p-2 shadow-xs outline-none"
              >
                <div class="flex items-center gap-2">
                  <RadioGroupItem id="female" value="female" class="after:absolute after:inset-0" />
                  <Label for="female">Female</Label>
                </div>
              </div>
            </RadioGroup>
            <FieldError :errors="errors.gender" />
          </Field>

          <Field :data-invalid="!!errors?.birth_date">
            <FieldLabel for="birth_date">Birth Date</FieldLabel>
            <DatePicker
              v-model="form.birth_date"
              disable-future-dates
              placeholder="Pick your birth date"
            />
            <FieldError :errors="errors.birth_date" />
          </Field>

          <Field :data-invalid="!!errors?.bio">
            <FieldLabel for="bio">Bio</FieldLabel>
            <Textarea id="bio" v-model="form.bio" maxlength="1000" :aria-invalid="!!errors?.bio" />
            <FieldError :errors="errors.bio" />
          </Field>
        </div>
      </div>
    </div>

    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Images</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <Field :data-invalid="!!errors?.tmp_profile_image">
            <FieldLabel>Profile Image</FieldLabel>
            <InputFileImage
              ref="profileImageInputRef"
              v-model="imageFiles.profile_image"
              :initial-image="initialData?.profile_image"
              v-model:delete-flag="deleteFlags.profile_image"
              container-class="squircle relative isolate aspect-square max-w-40"
            />
            <FieldError :errors="errors.tmp_profile_image" />
          </Field>

          <Field :data-invalid="!!errors?.tmp_cover_image">
            <FieldLabel>Cover Image</FieldLabel>
            <InputFileImage
              ref="coverImageInputRef"
              v-model="imageFiles.cover_image"
              :initial-image="initialData?.cover_image"
              v-model:delete-flag="deleteFlags.cover_image"
            />
            <FieldError :errors="errors.tmp_cover_image" />
          </Field>
        </div>
      </div>
    </div>

    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Links</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-3">
          <div v-if="form.links.length > 0" class="space-y-2">
            <div v-for="(link, index) in form.links" :key="index" class="flex items-center gap-1.5">
              <div class="min-w-28 sm:min-w-36">
                <Select
                  v-model="link.label"
                  @update:model-value="(value) => handleLabelChange(index, value)"
                >
                  <div v-if="link.isCustomLabel" class="relative">
                    <Input
                      v-model="link.label"
                      type="text"
                      placeholder="Enter custom label"
                      class="pr-7"
                    />
                    <SelectTrigger
                      class="absolute top-0 right-0 flex size-8 items-center justify-center border-transparent bg-transparent !p-0 [&_svg]:!m-0"
                    />
                  </div>
                  <SelectTrigger v-else class="w-full">
                    <SelectValue placeholder="Select label" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="label in PREDEFINED_LINK_LABELS" :key="label" :value="label">
                      {{ label }}
                    </SelectItem>
                    <SelectItem value="Custom">Custom</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <InputLink v-model="link.url" :label="link.label" class="grow" />

              <button
                type="button"
                @click="removeLink(index)"
                class="text-destructive-foreground hover:text-destructive-foreground/80 flex size-9 items-center justify-center rounded-lg transition"
              >
                <Icon name="hugeicons:delete-01" class="size-4" />
              </button>
            </div>
          </div>

          <button
            type="button"
            @click="addLink"
            class="text-primary hover:text-primary/80 flex items-center gap-x-1 py-1 text-sm font-medium tracking-tight transition"
          >
            <Icon name="hugeicons:add-01" class="size-4" />
            Add Link
          </button>
          <FieldError :errors="errors.links" />
        </div>
      </div>
    </div>

    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Account Settings</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <Field v-if="showPassword" :data-invalid="!!errors?.password">
            <FieldLabel for="password">Password</FieldLabel>
            <InputPassword
              :aria-invalid="!!errors?.password"
              id="password"
              v-model="form.password"
              minlength="8"
              autocomplete="new-password"
            />
            <p class="text-muted-foreground text-xs tracking-tight">
              {{
                isCreate
                  ? "Optional. User can log in via magic link if no password is set."
                  : "Leave empty to keep current password."
              }}
            </p>
            <FieldError :errors="errors.password" />
          </Field>

          <div v-if="showAccountSettings" class="space-y-5">
            <div class="grid grid-cols-2 gap-3">
              <Field :data-invalid="!!errors?.status">
                <FieldLabel for="status">Status</FieldLabel>
                <Select v-model="form.status">
                  <SelectTrigger class="w-full" :aria-invalid="!!errors?.status">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="active">Active</SelectItem>
                    <SelectItem value="inactive">Inactive</SelectItem>
                    <SelectItem value="suspended">Suspended</SelectItem>
                  </SelectContent>
                </Select>
                <FieldError :errors="errors.status" />
              </Field>

              <ProfileVisibilityField v-model="form.visibility" :errors="errors.visibility" />
            </div>

            <Field v-if="showRoles" :data-invalid="!!errors?.roles">
              <FieldLabel>Roles</FieldLabel>
              <div class="space-y-2">
                <div v-for="role in roles" :key="role.id" class="flex items-center gap-2">
                  <Checkbox
                    :id="`role-${role.id}`"
                    :model-value="form.roles.includes(role.name)"
                    @update:model-value="(checked) => toggleRole(role.name, checked)"
                  />
                  <Label :for="`role-${role.id}`" class="cursor-pointer font-normal capitalize">
                    {{ role.name }}
                  </Label>
                </div>
              </div>
              <FieldError :errors="errors.roles" />
            </Field>
          </div>

          <ProfileVisibilityField
            v-if="!showAccountSettings"
            v-model="form.visibility"
            :errors="errors.visibility"
            show-description
          />
        </div>
      </div>
    </div>

    <div v-if="showProjects" class="frame">
      <div class="frame-header">
        <div class="frame-title">Projects</div>
      </div>
      <div class="frame-panel">
        <Field :data-invalid="!!errors?.project_ids">
          <FieldLabel>Assigned Projects</FieldLabel>
          <ProjectMultiSelect
            v-if="projects.length"
            v-model="selectedProjects"
            :projects="projects"
            placeholder="Select projects"
            open-on-focus
          />
          <p v-else class="text-muted-foreground text-sm tracking-tight">No projects available</p>
          <FieldError :errors="errors.project_ids" />
        </Field>
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
import ProjectMultiSelect from "@/components/project/MultiSelect.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { toast } from "vue-sonner";

const { metaSymbol } = useShortcuts();

// Constants
const FILE_STATUS = {
  PROCESSING: 3,
};

// Helper functions
function createEmptyForm() {
  return {
    name: "",
    username: "",
    email: "",
    password: "",
    title: "",
    phone: "",
    birth_date: null,
    gender: "",
    bio: "",
    status: "active",
    visibility: "public",
    roles: [],
    links: seedPredefinedLinks([], PREDEFINED_LINK_LABELS),
    project_ids: [],
  };
}

function formatBirthDate(date) {
  if (!date) return null;
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}

const props = defineProps({
  initialData: {
    type: Object,
    default: () => ({}),
  },
  roles: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  errors: {
    type: Object,
    default: () => ({}),
  },
  isCreate: {
    type: Boolean,
    default: false,
  },
  showPassword: {
    type: Boolean,
    default: true,
  },
  showAccountSettings: {
    type: Boolean,
    default: false,
  },
  showRoles: {
    type: Boolean,
    default: false,
  },
  showImages: {
    type: Boolean,
    default: true,
  },
  showProjects: {
    type: Boolean,
    default: false,
  },
  projects: {
    type: Array,
    default: () => [],
  },
  submitText: {
    type: String,
    default: "Submit",
  },
  submitLoadingText: {
    type: String,
    default: "Submitting..",
  },
});

const emit = defineEmits(["submit"]);

const deleteFlags = ref({
  profile_image: false,
  cover_image: false,
});

// Uploaded files
const imageFiles = ref({
  profile_image: [],
  cover_image: [],
});

// Refs for InputFile components
const profileImageInputRef = ref(null);
const coverImageInputRef = ref(null);

// Form data
const form = reactive(createEmptyForm());

// Toggle role selection
function toggleRole(roleName, checked) {
  const index = form.roles.indexOf(roleName);
  const isChecked = checked === true || checked === "indeterminate";

  if (isChecked) {
    if (index === -1) {
      form.roles.push(roleName);
    }
  } else {
    if (index > -1) {
      form.roles.splice(index, 1);
    }
  }
}

// ProjectMultiSelect: selected projects
const selectedProjects = computed({
  get() {
    return (form.project_ids || []).map((id) => {
      const project = props.projects.find((p) => p.id === id);
      return project || { id, name: `Project #${id}` };
    });
  },
  set(projects) {
    form.project_ids = projects.map((p) => p.id);
  },
});

// Add new link
function addLink() {
  form.links.push({ label: "", url: "", isCustomLabel: false });
}

// Remove link
function removeLink(index) {
  form.links.splice(index, 1);
}

// Handle label change
function handleLabelChange(index, value) {
  if (value === "Custom") {
    form.links[index].isCustomLabel = true;
    form.links[index].label = "";
  } else {
    form.links[index].isCustomLabel = false;
    form.links[index].label = value;
  }
}

// Populate form with initial data
async function populateForm(data) {
  if (!data || Object.keys(data).length === 0) return;

  form.name = data.name || "";
  form.username = data.username || "";
  form.email = data.email || "";
  form.title = data.title || "";
  form.phone = data.phone || "";
  form.gender = data.gender || "";
  form.bio = data.bio || "";
  form.status = data.status || "active";
  form.visibility = data.visibility || "public";

  // Clear roles array first, then populate with new values
  form.roles.splice(0, form.roles.length);

  if (Array.isArray(data.roles)) {
    const roleNames = data.roles.map((role) => (typeof role === "string" ? role : role.name));
    form.roles.push(...roleNames);
  }

  // Handle links (exclude Email and WhatsApp as they're auto-generated)
  const savedLinks = Array.isArray(data.links)
    ? data.links.filter((link) => {
        const labelLower = link.label?.toLowerCase() || "";
        // Exclude Email and WhatsApp links
        return (
          labelLower !== "email" && labelLower !== "whatsapp" && !labelLower.startsWith("whatsapp ")
        );
      })
    : [];

  form.links.splice(0, form.links.length, ...seedPredefinedLinks(savedLinks, PREDEFINED_LINK_LABELS));

  // Handle projects
  form.project_ids = data.projects?.map((p) => p.id) || [];

  // Handle birth_date
  if (data.birth_date) {
    try {
      const [y, m, d] = data.birth_date.split("-").map(Number);
      form.birth_date = new Date(y, m - 1, d);
    } catch {
      form.birth_date = null;
    }
  } else {
    form.birth_date = null;
  }

  // Clear file uploads and reset delete flags
  imageFiles.value.profile_image = [];
  imageFiles.value.cover_image = [];
  deleteFlags.value.profile_image = false;
  deleteFlags.value.cover_image = false;
}

// Watch for initialData changes
watch(
  () => props.initialData,
  (newData) => {
    populateForm(newData);
  },
  { immediate: true }
);

// Check if any files are currently uploading
function hasFilesUploading() {
  return [profileImageInputRef, coverImageInputRef].some((ref) =>
    ref.value?.pond?.getFiles().some((file) => file.status === FILE_STATUS.PROCESSING)
  );
}

// Handle submit
function handleSubmit() {
  // Check if any files are still uploading
  if (hasFilesUploading()) {
    toast.error("Please wait until all files are uploaded");
    return;
  }
  // Filter out empty links and contact links (Email, WhatsApp)
  // Contact links are auto-generated by backend based on email/phone fields
  const filteredLinks = form.links.filter((link) => {
    if (!link.label || !link.url) return false; // Skip links missing a label or url

    const labelLower = link.label?.toLowerCase() || "";
    // Skip Email and WhatsApp as they are auto-generated
    if (labelLower === "email" || labelLower === "whatsapp" || labelLower.startsWith("whatsapp ")) {
      return false;
    }

    return true;
  });

  // Map links to only include label and url (remove isCustomLabel flag)
  const formattedLinks = filteredLinks.map((link) => ({
    label: link.label,
    url: link.url,
  }));

  const payload = {
    ...form,
    birth_date: formatBirthDate(form.birth_date),
    gender: form.gender || null, // Convert empty string to null for PostgreSQL constraint
    links: formattedLinks,
  };

  // Add file uploads if images are shown
  if (props.showImages) {
    // Handle profile image
    const profileValue = imageFiles.value.profile_image?.[0];
    if (profileValue && profileValue.startsWith("tmp-")) {
      // User uploaded new image
      payload.tmp_profile_image = profileValue;
    } else if (deleteFlags.value.profile_image && !profileValue) {
      // User clicked delete and didn't upload new image
      payload.delete_profile_image = true;
    }

    // Handle cover image
    const coverValue = imageFiles.value.cover_image?.[0];
    if (coverValue && coverValue.startsWith("tmp-")) {
      // User uploaded new image
      payload.tmp_cover_image = coverValue;
    } else if (deleteFlags.value.cover_image && !coverValue) {
      // User clicked delete and didn't upload new image
      payload.delete_cover_image = true;
    }
  }

  // Remove password if empty and not creating
  if (!props.isCreate && !payload.password) {
    delete payload.password;
  }

  // Remove project_ids if projects section is not shown
  if (!props.showProjects) {
    delete payload.project_ids;
  }

  emit("submit", payload);
}

// Expose form data and methods
defineExpose({
  form,
  imageFiles,
  handleSubmit,
});

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      handleSubmit();
    },
  },
});
</script>
