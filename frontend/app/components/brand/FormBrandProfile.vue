<template>
  <form @submit.prevent="save" class="grid gap-y-8">
    <!-- Brand Information -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">{{ $t("brandsForm.brandInformation") }}</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-4">
          <div v-if="showLogo" class="grid grid-cols-1 gap-y-6 sm:grid-cols-2 sm:gap-x-6">
            <!-- Profile Image (square avatar) -->
            <div class="space-y-2">
              <Label>Profile Image</Label>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Shown as your brand's avatar on the exhibitors list and your brand page.
              </p>
              <InputFileImage
                v-model="profileImageFiles"
                v-model:deleteFlag="deleteProfileImage"
                :initial-image="brand?.profile_image"
                :min-dimension="1000"
                allow-svg
                container-class="relative isolate size-32 rounded-lg"
                image-class="border-border size-full rounded-lg border object-contain"
                :container-style="checkerboardStyle"
                @preview-url="(url) => (previewImageUrl = url)"
              />
              <ul class="text-muted-foreground space-y-1 text-xs tracking-tight sm:text-sm">
                <li class="flex items-center gap-1.5">
                  <Icon name="hugeicons:aspect-ratio" class="size-3.5 shrink-0" />
                  Square, at least 1000x1000px
                </li>
                <li class="flex items-center gap-1.5">
                  <Icon name="hugeicons:image-01" class="size-3.5 shrink-0" />
                  JPG, PNG, WebP, or SVG
                </li>
                <li class="flex items-center gap-1.5">
                  <Icon name="hugeicons:file-validation" class="size-3.5 shrink-0" />
                  Max 20MB
                </li>
                <li class="flex items-start gap-1.5">
                  <Icon name="hugeicons:alert-circle" class="mt-0.5 size-3.5 shrink-0" />
                  <span>Use a solid background - a transparent logo can disappear against light or dark pages.</span>
                </li>
              </ul>
            </div>

            <!-- Brand Logo (raw master file) -->
            <div class="space-y-2">
              <Label>Brand Logo</Label>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                The master file our design team uses for banners, social media, and print. Upload
                the highest resolution you have - we store it exactly as uploaded, without
                compression.
              </p>
              <InputFileDownloadCard
                v-model="logoFiles"
                v-model:deleteFlag="deleteLogo"
                :initial-file="brand?.brand_logo"
                :accepted-file-types="brandLogoAcceptedTypes"
                skip-optimize
              />
              <ul class="text-muted-foreground space-y-1 text-xs tracking-tight sm:text-sm">
                <li class="flex items-center gap-1.5">
                  <Icon name="hugeicons:crop" class="size-3.5 shrink-0" />
                  Any shape - no ratio requirement
                </li>
                <li class="flex items-center gap-1.5">
                  <Icon name="hugeicons:file-01" class="size-3.5 shrink-0" />
                  JPG, PNG, WebP, SVG, PDF, AI, or ZIP
                </li>
                <li class="flex items-center gap-1.5">
                  <Icon name="hugeicons:file-validation" class="size-3.5 shrink-0" />
                  Max 20MB
                </li>
                <li class="flex items-center gap-1.5">
                  <Icon name="hugeicons:view-off" class="size-3.5 shrink-0" />
                  Not shown anywhere on the website.
                </li>
              </ul>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="brand_name">{{ $t("brandsForm.brandName") }}</Label>
            <Input id="brand_name" v-model="form.name" />
          </div>

          <div v-if="showCategories" class="space-y-2">
            <Label>{{ $t("brandsForm.businessCategories") }}</Label>
            <MultiSelect
              v-if="businessCategoryOptions.length"
              v-model="selectedCategoryOptions"
              :options="availableCategoryOptions"
              :placeholder="$t('brandsForm.addCategory')"
              open-on-focus
            />
            <TagsInput v-else v-model="form.business_categories" class="text-sm">
              <TagsInputItem v-for="cat in form.business_categories" :key="cat" :value="cat">
                <TagsInputItemText />
                <TagsInputItemDelete />
              </TagsInputItem>
              <TagsInputInput :placeholder="$t('brandsForm.addCategory')" />
            </TagsInput>
          </div>

          <div class="space-y-2">
            <Label for="company_name">{{ $t("brandsForm.companyName") }}</Label>
            <Input id="company_name" v-model="form.company_name" />
          </div>

          <AddressFields v-model="form.address" :errors="errors" />

          <div class="grid grid-cols-1 gap-x-2 gap-y-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="company_email">{{ $t("brandsForm.companyEmail") }}</Label>
              <Input id="company_email" v-model="form.company_email" type="email" />
            </div>
            <div class="space-y-2">
              <Label for="company_phone">{{ $t("brandsForm.companyPhone") }}</Label>
              <InputPhone v-model="form.company_phone" id="company_phone" />
            </div>
          </div>

          <div class="space-y-2">
            <Label for="description">{{ $t("brandsForm.description") }}</Label>
            <TipTapEditor
              v-model="form.description"
              model-type="App\Models\Brand"
              collection="description_images"
              :sticky="false"
              min-height="200px"
              :placeholder="$t('brandsForm.writeBrandDescription')"
            />
          </div>

          <!-- Custom Fields -->
          <CustomFieldGroup
            v-if="customFieldDefinitions?.length"
            :fields="customFieldDefinitions"
            :model-value="customFieldValues"
            :errors="customFieldErrors"
            error-prefix="project_custom_fields."
            value-key="key"
            @update:model-value="onCustomFieldsUpdate"
          />

          <div v-if="showStatus" class="space-y-2">
            <Label for="status">{{ $t("brandsForm.status") }}</Label>
            <Select v-model="form.status">
              <SelectTrigger id="status" class="w-40">
                <SelectValue :placeholder="$t('brandsForm.selectStatus')" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="active">{{ $t("brandsForm.active") }}</SelectItem>
                <SelectItem value="inactive">{{ $t("brandsForm.inactive") }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
      </div>
    </div>

    <!-- Links -->
    <div v-if="showLinks" class="frame">
      <div class="frame-header">
        <div class="frame-title">Links</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-3">
          <div v-if="brandLinks.length > 0" class="space-y-2">
            <div v-for="(link, index) in brandLinks" :key="index" class="flex items-center gap-1.5">
              <div class="min-w-28 sm:min-w-36">
                <Select
                  v-model="link.label"
                  @update:model-value="(value) => handleLinkLabelChange(index, value)"
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
        </div>
      </div>
    </div>

    <div>
      <Button type="submit" :disabled="saving" size="sm">
        <Icon v-if="saving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
        Save
        <KbdGroup>
          <Kbd>{{ metaSymbol }}</Kbd>
          <Kbd>S</Kbd>
        </KbdGroup>
      </Button>
    </div>
  </form>
</template>

<script setup>
import AddressFields from "@/components/AddressFields.vue";
import { CustomFieldGroup } from "@/components/ui/custom-field";
import { formatResponseValue, localizedLabel } from "@/components/ui/custom-field/core";
import { TipTapEditor } from "@/components/ui/tip-tap-editor";
import { MultiSelect } from "@/components/ui/multi-select";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";
import { toast } from "vue-sonner";

const { t } = useI18n();

const props = defineProps({
  brand: { type: Object, default: null },
  apiUrl: { type: String, required: true },
  showLogo: { type: Boolean, default: false },
  showStatus: { type: Boolean, default: false },
  showCategories: { type: Boolean, default: false },
  showLinks: { type: Boolean, default: false },
  businessCategoryOptions: { type: Array, default: () => [] },
  customFieldDefinitions: { type: Array, default: () => [] },
  customFieldInitialValues: { type: Object, default: () => ({}) },
  livePreview: { type: Boolean, default: false },
  previewBoothNumber: { type: String, default: null },
});
const emit = defineEmits(["saved", "update:previewData"]);
const client = useSanctumClient();

// Object URL of a freshly picked avatar, shown in the live preview before save.
const previewImageUrl = ref(null);

const saving = ref(false);
const { metaSymbol } = useShortcuts();
const profileImageFiles = ref([]);
const deleteProfileImage = ref(false);
const logoFiles = ref([]);
const deleteLogo = ref(false);

// Raw master-logo collection accepts images plus print/design assets.
const brandLogoAcceptedTypes = [
  "image/jpeg",
  "image/png",
  "image/webp",
  "image/svg+xml",
  "application/pdf",
  "application/postscript",
  "application/illustrator",
  "application/zip",
  "application/x-zip-compressed",
];

// Checkerboard behind the avatar preview so transparent logos are obvious.
const checkerboardStyle = {
  backgroundImage:
    "linear-gradient(45deg, var(--color-muted) 25%, transparent 25%), linear-gradient(-45deg, var(--color-muted) 25%, transparent 25%), linear-gradient(45deg, transparent 75%, var(--color-muted) 75%), linear-gradient(-45deg, transparent 75%, var(--color-muted) 75%)",
  backgroundSize: "16px 16px",
  backgroundPosition: "0 0, 0 8px, 8px -8px, -8px 0px",
};

const form = reactive({
  name: props.brand?.name || "",
  company_name: props.brand?.company_name || "",
  company_email: props.brand?.company_email || "",
  company_phone: props.brand?.company_phone || "",
  address: {
    street: props.brand?.address?.street || "",
    city: props.brand?.address?.city || "",
    province: props.brand?.address?.province || "",
    country: props.brand?.address?.country || "",
  },
  description: props.brand?.description || "",
  status: props.brand?.status || "active",
  business_categories: props.brand?.business_categories || [],
});

// MultiSelect: convert string[] options to Option[] format
const availableCategoryOptions = computed(() =>
  props.businessCategoryOptions.map((name) => ({ value: name, label: name }))
);

// MultiSelect: two-way sync between form.business_categories (string[]) and Option[]
const selectedCategoryOptions = computed({
  get() {
    return (form.business_categories || []).map((name) => ({ value: name, label: name }));
  },
  set(options) {
    form.business_categories = options.map((opt) => opt.value);
  },
});

// Links
const PREDEFINED_LINK_LABELS = ["Website", "Instagram", "Facebook", "X", "TikTok", "LinkedIn", "YouTube"];

const brandLinks = reactive(
  (props.brand?.links || []).map((link) => ({
    label: link.label || "",
    url: link.url || "",
    isCustomLabel: !PREDEFINED_LINK_LABELS.includes(link.label),
  }))
);

function addLink() {
  brandLinks.push({ label: "", url: "", isCustomLabel: false });
}

function removeLink(index) {
  brandLinks.splice(index, 1);
}

function handleLinkLabelChange(index, value) {
  if (value === "Custom") {
    brandLinks[index].isCustomLabel = true;
    brandLinks[index].label = "";
  } else {
    brandLinks[index].isCustomLabel = false;
    brandLinks[index].label = value;
  }
}

// Custom field values (reactive object keyed by field key) + server-side
// validation errors surfaced by the CustomFieldGroup, keyed
// project_custom_fields.{key}.
const customFieldValues = reactive(
  normalizeCustomFieldValues(props.customFieldInitialValues, props.customFieldDefinitions)
);
const customFieldErrors = ref({});
const errors = ref({});

function onCustomFieldsUpdate(next) {
  Object.assign(customFieldValues, next);
}

// Live preview: emit a snapshot of the form the public site would consume.
const savedProfileImageUrl = computed(() => {
  const img = props.brand?.profile_image;
  return img?.lg || img?.url || null;
});

// Every non-empty custom field, in definition order, formatted the same way
// the public brand page reads them (matches PublicBrandDetailResource).
const previewCustomFields = computed(() =>
  (props.customFieldDefinitions || [])
    .filter((def) => def.type !== "section" && def.is_public !== false)
    .map((def) => ({
      key: def.key,
      label: localizedLabel(def.label),
      value: formatResponseValue(def, customFieldValues[def.key]),
    }))
    .filter((field) => field.value && field.value !== "-")
);

const previewData = computed(() => ({
  brand_name: form.name,
  company_name: form.company_name,
  profile_image_url: previewImageUrl.value || savedProfileImageUrl.value,
  business_categories: [...(form.business_categories || [])],
  links: brandLinks
    .filter((link) => link.label && link.url)
    .map((link) => ({ label: link.label, url: link.url })),
  booth_number: props.previewBoothNumber,
  custom_fields: previewCustomFields.value,
  description_html: form.description,
  promotions: [],
}));

watch(
  previewData,
  (value) => {
    if (props.livePreview) emit("update:previewData", value);
  },
  { deep: true, immediate: true }
);

function normalizeCustomFieldValues(values, definitions) {
  const result = { ...values };
  for (const field of definitions || []) {
    // Legacy 'year_select' + the migrated select+years preset both store the
    // year as a string.
    const isYear = field.type === "year_select" || field.settings?.options_preset === "years";
    if (isYear && result[field.key] != null) {
      result[field.key] = String(result[field.key]);
    }
  }
  return result;
}

// Sync form when brand prop changes
watch(
  () => props.brand,
  (newBrand) => {
    if (newBrand) {
      form.name = newBrand.name || "";
      form.company_name = newBrand.company_name || "";
      form.company_email = newBrand.company_email || "";
      form.company_phone = newBrand.company_phone || "";
      form.address = {
        street: newBrand.address?.street || "",
        city: newBrand.address?.city || "",
        province: newBrand.address?.province || "",
        country: newBrand.address?.country || "",
      };
      form.description = newBrand.description || "";
      form.status = newBrand.status || "active";
      form.business_categories = newBrand.business_categories || [];

      // Sync links
      brandLinks.splice(0, brandLinks.length);
      if (newBrand.links?.length) {
        brandLinks.push(
          ...newBrand.links.map((link) => ({
            label: link.label || "",
            url: link.url || "",
            isCustomLabel: !PREDEFINED_LINK_LABELS.includes(link.label),
          }))
        );
      }
    }
  }
);

// Sync custom field values when prop changes
watch(
  () => props.customFieldInitialValues,
  (newValues) => {
    if (newValues) {
      const normalized = normalizeCustomFieldValues(newValues, props.customFieldDefinitions);
      Object.keys(normalized).forEach((key) => {
        customFieldValues[key] = normalized[key];
      });
    }
  }
);

async function save() {
  saving.value = true;
  try {
    const body = { ...form };

    // Clean address - send null if all empty
    const addr = body.address;
    if (!addr.street && !addr.city && !addr.province && !addr.country) {
      body.address = null;
    }

    // Only include status/categories if shown
    if (!props.showStatus) {
      delete body.status;
    }
    if (!props.showCategories) {
      delete body.business_categories;
    }

    // Handle asset uploads (avatar + raw master logo)
    if (props.showLogo) {
      if (profileImageFiles.value?.[0] && typeof profileImageFiles.value[0] === "string") {
        body.tmp_profile_image = profileImageFiles.value[0];
      }
      if (deleteProfileImage.value) {
        body.delete_profile_image = true;
      }
      if (logoFiles.value?.[0] && typeof logoFiles.value[0] === "string") {
        body.tmp_brand_logo = logoFiles.value[0];
      }
      if (deleteLogo.value) {
        body.delete_brand_logo = true;
      }
    }

    // Include links if shown
    if (props.showLinks) {
      body.links = brandLinks
        .filter((link) => link.label && link.url)
        .map((link) => ({ label: link.label, url: link.url }));
    }

    // Include custom field values if definitions exist
    if (props.customFieldDefinitions?.length) {
      body.project_custom_fields = { ...customFieldValues };
    }

    customFieldErrors.value = {};
    errors.value = {};
    await client(props.apiUrl, { method: "PUT", body });
    toast.success(t("brandsForm.brandUpdated"));

    // Reset asset state
    if (props.showLogo) {
      profileImageFiles.value = [];
      deleteProfileImage.value = false;
      logoFiles.value = [];
      deleteLogo.value = false;
    }

    emit("saved");
  } catch (e) {
    // Surface per-field custom-field validation (project_custom_fields.{key}).
    customFieldErrors.value = e?.data?.errors || {};
    errors.value = e?.data?.errors || {};
    toast.error(e?.data?.message || t("brandsForm.failedToUpdate"));
  } finally {
    saving.value = false;
  }
}

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      save();
    },
  },
});
</script>
