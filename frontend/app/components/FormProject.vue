<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-6">
    <div class="space-y-5">
      <div class="space-y-4">
        <Label>Profile Image</Label>
        <InputFileImage
          ref="profileImageInputRef"
          v-model="imageFiles.profile_image"
          :initial-image="initialData?.profile_image"
          v-model:delete-flag="deleteFlags.profile_image"
          container-class="squircle relative isolate aspect-square max-w-40"
        />
        <InputErrorMessage :errors="errors.tmp_profile_image" />
      </div>

      <div class="space-y-4">
        <Label>Cover Image</Label>
        <InputFileImage
          ref="coverImageInputRef"
          v-model="imageFiles.cover_image"
          :initial-image="initialData?.cover_image"
          v-model:delete-flag="deleteFlags.cover_image"
        />
        <InputErrorMessage :errors="errors.tmp_cover_image" />
      </div>

      <h3 class="text-muted-foreground text-sm font-medium tracking-tight">Project Information</h3>

      <div class="space-y-2">
        <Label for="name">Project Name *</Label>
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
        <Label for="email">Email Address</Label>
        <Input id="email" v-model="form.email" type="email" />
        <InputErrorMessage :errors="errors.email" />
      </div>

      <div class="space-y-2">
        <Label for="phone">Phone Number</Label>
        <InputPhone v-model="form.phone" id="phone" />
        <InputErrorMessage :errors="errors.phone" />
      </div>

      <div class="space-y-2">
        <Label for="bio">Description</Label>
        <Textarea id="bio" v-model="form.bio" maxlength="1000" />
        <InputErrorMessage :errors="errors.bio" />
      </div>

      <div class="space-y-2">
        <Label>Members</Label>
        <UserMultiSelect
          :users="eligibleMembers"
          v-model="selectedMembers"
          v-model:query="memberQuery"
          placeholder="Search members..."
          :hide-clear-all-button="true"
        />
        <InputErrorMessage :errors="errors.member_ids" />
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

      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-2">
          <Label for="status">Status</Label>
          <Select v-model="form.status">
            <SelectTrigger class="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="draft">Draft</SelectItem>
              <SelectItem value="active">Active</SelectItem>
              <SelectItem value="archived">Archived</SelectItem>
            </SelectContent>
          </Select>
          <InputErrorMessage :errors="errors.status" />
        </div>

        <div class="space-y-2">
          <Label for="visibility">Visibility</Label>
          <Select v-model="form.visibility">
            <SelectTrigger class="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="public">Public</SelectItem>
              <SelectItem value="private">Private</SelectItem>
              <SelectItem value="members_only">Members Only</SelectItem>
            </SelectContent>
          </Select>
          <InputErrorMessage :errors="errors.visibility" />
        </div>
      </div>
    </div>

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

const memberQuery = ref("");
const selectedMembers = ref([]);

// Helper functions
function createEmptyForm() {
  return {
    name: "",
    username: "",
    email: "",
    phone: "",
    bio: "",
    status: "active",
    visibility: "public",
    member_ids: [],
    links: [],
  };
}

const props = defineProps({
  initialData: {
    type: Object,
    default: () => ({}),
  },
  eligibleMembers: {
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

// Watch selectedMembers and sync with form.member_ids
watch(
  selectedMembers,
  (newValue) => {
    form.member_ids = newValue.map((user) => user.id);
  },
  { deep: true }
);

// Add link
function addLink() {
  form.links.push({
    label: "",
    url: "",
    isCustomLabel: false,
  });
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
function populateForm(data) {
  if (!data || Object.keys(data).length === 0) return;

  form.name = data.name || "";
  form.username = data.username || "";
  form.email = data.email || "";
  form.phone = data.phone || "";
  form.bio = data.bio || "";
  form.status = data.status || "active";
  form.visibility = data.visibility || "public";

  // Handle members
  form.member_ids = [];
  selectedMembers.value = [];
  if (Array.isArray(data.members) && data.members.length > 0) {
    form.member_ids = data.members.map((member) => member.id);
    selectedMembers.value = data.members;
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
    links: formattedLinks,
  };

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
