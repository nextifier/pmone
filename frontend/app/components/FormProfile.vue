<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-6">
    <div class="space-y-5">
      <!-- Profile and Cover Images -->
      <div v-if="showImages" class="space-y-5">
        <ImageUploadField
          ref="profileImageInputRef"
          label="Profile Image"
          v-model="imageFiles.profile_image"
          :initial-image="initialData?.profile_image"
          v-model:delete-flag="deleteFlags.profile_image"
          :errors="errors.tmp_profile_image"
          container-class="squircle relative isolate aspect-square max-w-40"
        />

        <ImageUploadField
          ref="coverImageInputRef"
          label="Cover Image"
          v-model="imageFiles.cover_image"
          :initial-image="initialData?.cover_image"
          v-model:delete-flag="deleteFlags.cover_image"
          :errors="errors.tmp_cover_image"
        />
      </div>

      <h3 class="text-muted-foreground text-sm font-medium tracking-tight">Personal Information</h3>

      <div class="space-y-2">
        <Label for="name">Full Name *</Label>
        <Input id="name" v-model="form.name" type="text" required />
        <InputErrorMessage :errors="errors.name" />
      </div>

      <div class="space-y-2">
        <div class="flex items-center justify-between gap-x-2">
          <Label for="username">Username</Label>
          <p class="text-muted-foreground line-clamp-1 text-xs tracking-tight">
            {{ isCreate ? "Will be auto-generated if left empty." : "" }}
          </p>
        </div>
        <Input id="username" v-model="form.username" type="text" :required="!isCreate" />
        <InputErrorMessage :errors="errors.username" />
      </div>

      <div class="space-y-2">
        <Label for="email">Email Address *</Label>
        <Input id="email" v-model="form.email" type="email" required />
        <InputErrorMessage :errors="errors.email" />
      </div>

      <div class="space-y-2">
        <Label for="phone">Phone Number</Label>
        <InputPhone v-model="form.phone" id="phone" />
        <InputErrorMessage :errors="errors.phone" />
      </div>

      <div v-if="showPassword" class="space-y-2">
        <div class="flex items-center justify-between gap-x-2">
          <Label for="password">Password {{ isCreate ? "*" : "" }}</Label>
          <p class="text-muted-foreground text-xs tracking-tight">
            {{ isCreate ? "Minimum 8 characters." : "Leave empty to keep current password." }}
          </p>
        </div>
        <Input
          id="password"
          v-model="form.password"
          type="password"
          :required="isCreate"
          minlength="8"
        />
        <InputErrorMessage :errors="errors.password" />
      </div>

      <div class="space-y-2">
        <Label for="birth_date">Birth Date</Label>
        <Popover>
          <PopoverTrigger as-child>
            <Button
              variant="outline"
              :class="
                cn(
                  'w-full justify-start text-left font-normal',
                  !form.birth_date && 'text-muted-foreground'
                )
              "
            >
              <Icon name="hugeicons:calendar-04" class="size-4" />
              {{
                form.birth_date
                  ? df.format(form.birth_date.toDate(getLocalTimeZone()))
                  : "Pick your birth date"
              }}
            </Button>
          </PopoverTrigger>
          <PopoverContent class="w-auto p-0">
            <CalendarMonthYearSelect
              v-model="form.birth_date"
              :disable-future-dates="true"
              initial-focus
            />
          </PopoverContent>
        </Popover>
        <InputErrorMessage :errors="errors.birth_date" />
      </div>

      <div class="space-y-2">
        <Label for="gender">Gender</Label>

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
        <InputErrorMessage :errors="errors.gender" />
      </div>

      <div class="space-y-2">
        <Label for="bio">Bio</Label>
        <Textarea id="bio" v-model="form.bio" maxlength="1000" />
        <InputErrorMessage :errors="errors.bio" />
      </div>

      <div class="space-y-3">
        <h3 class="text-muted-foreground text-sm font-medium tracking-tight">Links</h3>

        <div v-if="form.links.length > 0" class="space-y-2">
          <div v-for="(link, index) in form.links" :key="index" class="flex items-center gap-1.5">
            <div class="min-w-42">
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
                  <SelectItem v-for="label in PREDEFINED_LABELS" :key="label" :value="label">
                    {{ label }}
                  </SelectItem>
                  <SelectItem value="Custom">Custom</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <Input v-model="link.url" type="url" placeholder="Enter URL" class="grow" />

            <button
              type="button"
              @click="removeLink(index)"
              class="text-destructive hover:text-destructive/80 flex size-9 items-center justify-center rounded-lg transition"
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
        <InputErrorMessage :errors="errors.links" />
      </div>
    </div>

    <div v-if="showAccountSettings" class="space-y-5">
      <h3 class="text-muted-foreground text-sm font-medium tracking-tight">Account Settings</h3>

      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-2">
          <Label for="status">Status</Label>
          <Select v-model="form.status">
            <SelectTrigger class="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="active">Active</SelectItem>
              <SelectItem value="inactive">Inactive</SelectItem>
              <SelectItem value="suspended">Suspended</SelectItem>
            </SelectContent>
          </Select>
          <InputErrorMessage :errors="errors.status" />
        </div>

        <ProfileVisibilityField v-model="form.visibility" :errors="errors.visibility" />
      </div>

      <div v-if="showRoles" class="space-y-2">
        <Label>Roles</Label>
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
        <InputErrorMessage :errors="errors.roles" />
      </div>
    </div>

    <ProfileVisibilityField
      v-if="!showAccountSettings"
      v-model="form.visibility"
      :errors="errors.visibility"
      show-description
    />

    <div class="flex justify-end gap-x-3">
      <button
        type="submit"
        :disabled="loading"
        class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-semibold tracking-tighter transition disabled:opacity-50"
      >
        <Spinner v-if="loading" />
        {{ loading ? submitLoadingText : submitText }}
      </button>
    </div>
  </form>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { DateFormatter, getLocalTimeZone } from "@internationalized/date";
import { toast } from "vue-sonner";

// Constants
const PREDEFINED_LABELS = [
  "Website",
  "Instagram",
  "Facebook",
  "X",
  "TikTok",
  "LinkedIn",
  "YouTube",
];
const FILE_STATUS = {
  PROCESSING: 3,
};

const df = new DateFormatter("en-US", {
  dateStyle: "long",
});

// Helper functions
function createEmptyForm() {
  return {
    name: "",
    username: "",
    email: "",
    password: "",
    phone: "",
    birth_date: "",
    gender: "",
    bio: "",
    status: "active",
    visibility: "public",
    roles: [],
    links: [],
  };
}

function formatBirthDate(date) {
  if (!date) return null;
  return `${date.year}-${String(date.month).padStart(2, "0")}-${String(date.day).padStart(2, "0")}`;
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

  // Handle links
  form.links.splice(0, form.links.length);

  if (Array.isArray(data.links) && data.links.length > 0) {
    const formattedLinks = data.links.map((link) => ({
      label: link.label || "",
      url: link.url || "",
      isCustomLabel: !PREDEFINED_LABELS.includes(link.label),
    }));

    form.links.push(...formattedLinks);
  }

  // Handle birth_date
  if (data.birth_date) {
    const { parseDate } = await import("@internationalized/date");
    try {
      form.birth_date = parseDate(data.birth_date);
    } catch {
      form.birth_date = "";
    }
  } else {
    form.birth_date = "";
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
  // Filter out empty links (both label and url are empty)
  const filteredLinks = form.links.filter((link) => link.label || link.url);

  // Map links to only include label and url (remove isCustomLabel flag)
  const formattedLinks = filteredLinks.map((link) => ({
    label: link.label,
    url: link.url,
  }));

  const payload = {
    ...form,
    birth_date: formatBirthDate(form.birth_date),
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
    handler: () => {
      handleSubmit();
    },
  },
});
</script>
