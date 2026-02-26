<template>
  <form @submit.prevent="save" class="grid gap-y-8">
    <!-- Brand Information -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">{{ $t('brandsForm.brandInformation') }}</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div v-if="showLogo" class="space-y-2">
            <Label>{{ $t('brandsForm.brandLogo') }}</Label>
            <InputFileImage
              v-model="logoFiles"
              v-model:deleteFlag="deleteLogo"
              :initial-image="brand?.brand_logo"
              container-class="relative isolate size-32"
            />
          </div>

          <div class="space-y-2">
            <Label for="brand_name">{{ $t('brandsForm.brandName') }}</Label>
            <Input id="brand_name" v-model="form.name" />
          </div>

          <div v-if="showCategories" class="space-y-2">
            <Label>{{ $t('brandsForm.businessCategories') }}</Label>
            <TagsInput v-model="form.business_categories" class="text-sm">
              <TagsInputItem
                v-for="cat in form.business_categories"
                :key="cat"
                :value="cat"
              >
                <TagsInputItemText />
                <TagsInputItemDelete />
              </TagsInputItem>
              <TagsInputInput :placeholder="$t('brandsForm.addCategory')" />
            </TagsInput>
            <p class="text-muted-foreground text-xs">
              {{ $t('brandsForm.pressEnterToAdd') }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="description">{{ $t('brandsForm.description') }}</Label>
            <TipTapEditor
              v-model="form.description"
              model-type="App\Models\Brand"
              collection="description_images"
              :sticky="false"
              min-height="200px"
              :placeholder="$t('brandsForm.writeBrandDescription')"
            />
          </div>

          <div v-if="showStatus" class="space-y-2">
            <Label for="status">{{ $t('brandsForm.status') }}</Label>
            <Select v-model="form.status">
              <SelectTrigger id="status" class="w-40">
                <SelectValue :placeholder="$t('brandsForm.selectStatus')" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="active">{{ $t('brandsForm.active') }}</SelectItem>
                <SelectItem value="inactive">{{ $t('brandsForm.inactive') }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
      </div>
    </div>

    <!-- Company Information -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">{{ $t('brandsForm.companyInformation') }}</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label for="company_name">{{ $t('brandsForm.companyName') }}</Label>
            <Input id="company_name" v-model="form.company_name" />
          </div>

          <div class="space-y-2">
            <Label for="company_address">{{ $t('brandsForm.companyAddress') }}</Label>
            <Textarea id="company_address" v-model="form.company_address" rows="2" />
          </div>

          <div class="grid grid-cols-1 gap-x-4 gap-y-6 lg:grid-cols-2">
            <div class="space-y-2">
              <Label for="company_email">{{ $t('brandsForm.companyEmail') }}</Label>
              <Input id="company_email" v-model="form.company_email" type="email" />
            </div>

            <div class="space-y-2">
              <Label for="company_phone">{{ $t('brandsForm.companyPhone') }}</Label>
              <InputPhone v-model="form.company_phone" id="company_phone" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Project-Specific Information (Custom Fields) -->
    <div v-if="customFieldDefinitions?.length" class="frame">
      <div class="frame-header">
        <div class="frame-title">{{ $t('brandsForm.projectSpecificInfo') }}</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div v-for="field in customFieldDefinitions" :key="field.id" class="space-y-2">
            <Label :for="`cf_${field.key}`">
              {{ field.label }}
              <span v-if="field.is_required" class="text-destructive">*</span>
            </Label>

            <!-- Text -->
            <Input
              v-if="field.type === 'text'"
              :id="`cf_${field.key}`"
              v-model="customFieldValues[field.key]"
            />

            <!-- Number -->
            <Input
              v-else-if="field.type === 'number'"
              :id="`cf_${field.key}`"
              v-model="customFieldValues[field.key]"
              type="number"
            />

            <!-- Textarea -->
            <Textarea
              v-else-if="field.type === 'textarea'"
              :id="`cf_${field.key}`"
              v-model="customFieldValues[field.key]"
              rows="3"
            />

            <!-- Select -->
            <Select
              v-else-if="field.type === 'select'"
              v-model="customFieldValues[field.key]"
            >
              <SelectTrigger :id="`cf_${field.key}`">
                <SelectValue placeholder="Select..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="option in field.options"
                  :key="option"
                  :value="option"
                >
                  {{ option }}
                </SelectItem>
              </SelectContent>
            </Select>

            <!-- Year Select -->
            <Select
              v-else-if="field.type === 'year_select'"
              v-model="customFieldValues[field.key]"
            >
              <SelectTrigger :id="`cf_${field.key}`">
                <SelectValue placeholder="Select year..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="year in yearOptions"
                  :key="year"
                  :value="String(year)"
                >
                  {{ year }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
      </div>
    </div>

    <div>
      <Button type="submit" :disabled="saving" size="sm">
        <Icon v-if="saving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
        {{ submitLabel }}
      </Button>
    </div>
  </form>
</template>

<script setup>
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
import TipTapEditor from "@/components/TipTapEditor.vue";
import { toast } from "vue-sonner";

const { t } = useI18n();

const props = defineProps({
  brand: { type: Object, default: null },
  apiUrl: { type: String, required: true },
  showLogo: { type: Boolean, default: false },
  showStatus: { type: Boolean, default: false },
  showCategories: { type: Boolean, default: false },
  submitLabel: { type: String, default: "Save Brand" },
  customFieldDefinitions: { type: Array, default: () => [] },
  customFieldInitialValues: { type: Object, default: () => ({}) },
});
const emit = defineEmits(["saved"]);
const client = useSanctumClient();

const saving = ref(false);
const logoFiles = ref([]);
const deleteLogo = ref(false);

const form = reactive({
  name: props.brand?.name || "",
  company_name: props.brand?.company_name || "",
  company_email: props.brand?.company_email || "",
  company_phone: props.brand?.company_phone || "",
  company_address: props.brand?.company_address || "",
  description: props.brand?.description || "",
  status: props.brand?.status || "active",
  business_categories: props.brand?.business_categories || [],
});

// Custom field values (reactive object keyed by field key)
const customFieldValues = reactive({ ...props.customFieldInitialValues });

// Year options (current year down to 1950)
const currentYear = new Date().getFullYear();
const yearOptions = computed(() => {
  const years = [];
  for (let y = currentYear; y >= 1950; y--) {
    years.push(y);
  }
  return years;
});

// Sync form when brand prop changes
watch(
  () => props.brand,
  (newBrand) => {
    if (newBrand) {
      form.name = newBrand.name || "";
      form.company_name = newBrand.company_name || "";
      form.company_email = newBrand.company_email || "";
      form.company_phone = newBrand.company_phone || "";
      form.company_address = newBrand.company_address || "";
      form.description = newBrand.description || "";
      form.status = newBrand.status || "active";
      form.business_categories = newBrand.business_categories || [];
    }
  }
);

// Sync custom field values when prop changes
watch(
  () => props.customFieldInitialValues,
  (newValues) => {
    if (newValues) {
      Object.keys(newValues).forEach((key) => {
        customFieldValues[key] = newValues[key];
      });
    }
  }
);

async function save() {
  saving.value = true;
  try {
    const body = { ...form };

    // Only include status/categories if shown
    if (!props.showStatus) {
      delete body.status;
    }
    if (!props.showCategories) {
      delete body.business_categories;
    }

    // Handle logo upload
    if (props.showLogo) {
      if (logoFiles.value?.[0] && typeof logoFiles.value[0] === "string") {
        body.tmp_brand_logo = logoFiles.value[0];
      }
      if (deleteLogo.value) {
        body.delete_brand_logo = true;
      }
    }

    // Include custom field values if definitions exist
    if (props.customFieldDefinitions?.length) {
      body.project_custom_fields = { ...customFieldValues };
    }

    await client(props.apiUrl, { method: "PUT", body });
    toast.success(t("brandsForm.brandUpdated"));

    // Reset logo state
    if (props.showLogo) {
      logoFiles.value = [];
      deleteLogo.value = false;
    }

    emit("saved");
  } catch (e) {
    toast.error(e?.data?.message || t("brandsForm.failedToUpdate"));
  } finally {
    saving.value = false;
  }
}
</script>
