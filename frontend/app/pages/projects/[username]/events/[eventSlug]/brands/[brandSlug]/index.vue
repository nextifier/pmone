<template>
  <div class="mx-auto flex max-w-2xl flex-col gap-y-6">
    <div class="flex items-start justify-between gap-x-2">
      <div class="space-y-1">
        <h3 class="page-title">Brand Details</h3>
        <p class="page-description">View and edit brand and booth information.</p>
      </div>
      <Button :disabled="saving" size="sm" @click="handleSubmit" class="shrink-0">
        <Icon v-if="saving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
        Save
        <KbdGroup>
          <Kbd>{{ metaSymbol }}</Kbd>
          <Kbd>S</Kbd>
        </KbdGroup>
      </Button>
    </div>

    <form @submit.prevent="handleSubmit" class="grid gap-y-8">
      <!-- Booth Information -->
      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Booth Information</div>
        </div>
        <div class="frame-panel">
          <div class="grid grid-cols-1 gap-y-4">
            <BrandBoothFields :form="boothForm" />

            <div class="grid grid-cols-2 gap-x-2 gap-y-4">
              <div class="space-y-2">
                <Label>Sales</Label>
                <Combobox v-model="boothForm.sales_id" :ignore-filter="true" :open-on-focus="true">
                  <ComboboxAnchor as-child class="w-full">
                    <div
                      class="border-border relative flex h-9 w-full items-center rounded-md border shadow-xs"
                    >
                      <ComboboxInputPrimitive
                        v-model="salesSearch"
                        :display-value="() => selectedSales?.name || ''"
                        placeholder="Select sales person"
                        class="placeholder:text-muted-foreground h-full w-full rounded-md bg-transparent px-3 text-sm tracking-tight outline-none"
                        autocomplete="off"
                      />
                      <button
                        v-if="boothForm.sales_id"
                        type="button"
                        class="text-muted-foreground hover:text-foreground absolute right-2 shrink-0"
                        @click="
                          boothForm.sales_id = null;
                          salesSearch = '';
                        "
                      >
                        <Icon name="lucide:x" class="size-3.5" />
                      </button>
                    </div>
                  </ComboboxAnchor>
                  <ComboboxList class="w-[var(--reka-combobox-trigger-width)]">
                    <ComboboxViewport>
                      <ComboboxEmpty>No results found.</ComboboxEmpty>
                      <ComboboxItem :value="null">
                        <div class="flex items-center gap-2">
                          <Icon name="hugeicons:border-none-02" class="size-5" />
                          <span>None</span>
                        </div>
                      </ComboboxItem>
                      <ComboboxItem v-for="user in filteredMembers" :key="user.id" :value="user.id">
                        <div class="flex items-center gap-2">
                          <Avatar
                            :model="user"
                            size="sm"
                            class="squircle size-5"
                            rounded="rounded-sm"
                          />
                          <span class="tracking-tight">{{ user.name }}</span>
                        </div>
                        <ComboboxItemIndicator>
                          <Icon name="lucide:check" class="ml-auto size-4" />
                        </ComboboxItemIndicator>
                      </ComboboxItem>
                    </ComboboxViewport>
                  </ComboboxList>
                </Combobox>
              </div>
              <div class="space-y-2">
                <Label>Status</Label>
                <Select v-model="boothForm.status">
                  <SelectTrigger class="w-full">
                    <SelectValue placeholder="Select status" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="active">Active</SelectItem>
                    <SelectItem value="draft">Draft</SelectItem>
                    <SelectItem value="cancelled">Cancelled</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div class="space-y-2">
              <Label for="notes">Notes</Label>
              <Textarea
                id="notes"
                v-model="boothForm.notes"
                rows="3"
                placeholder="Internal notes..."
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Brand Information -->
      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Brand Information</div>
        </div>
        <div class="frame-panel">
          <div class="grid grid-cols-1 gap-y-4">
            <div class="space-y-2">
              <Label>Brand Logo</Label>
              <InputFileImage
                v-model="logoFiles"
                v-model:deleteFlag="deleteLogo"
                :initial-image="brandEvent.brand?.brand_logo"
                container-class="relative isolate size-32"
              />
            </div>

            <div class="space-y-2">
              <Label for="brand_name">Brand Name</Label>
              <Input id="brand_name" v-model="brandForm.name" />
            </div>

            <div v-if="businessCategoryOptions.length" class="space-y-2">
              <Label>Business Categories</Label>
              <MultiSelect
                v-model="selectedCategoryOptions"
                :options="availableCategoryOptions"
                placeholder="Add category..."
                open-on-focus
              />
            </div>
            <div v-else class="space-y-2">
              <Label>Business Categories</Label>
              <TagsInput v-model="brandForm.business_categories" class="text-sm">
                <TagsInputItem v-for="cat in brandForm.business_categories" :key="cat" :value="cat">
                  <TagsInputItemText />
                  <TagsInputItemDelete />
                </TagsInputItem>
                <TagsInputInput placeholder="Add category..." />
              </TagsInput>
            </div>

            <div class="space-y-2">
              <Label for="company_name">Company Name</Label>
              <Input id="company_name" v-model="brandForm.company_name" />
            </div>

            <div class="space-y-2">
              <Label for="company_address">Company Address</Label>
              <Textarea id="company_address" v-model="brandForm.company_address" rows="2" />
            </div>

            <div class="grid grid-cols-1 gap-x-2 gap-y-4 sm:grid-cols-2">
              <div class="space-y-2">
                <Label for="company_email">Company Email</Label>
                <Input id="company_email" v-model="brandForm.company_email" type="email" />
              </div>
              <div class="space-y-2">
                <Label for="company_phone">Company Phone</Label>
                <InputPhone v-model="brandForm.company_phone" id="company_phone" />
              </div>
            </div>

            <div class="space-y-2">
              <Label for="description">Description</Label>
              <TipTapEditor
                v-model="brandForm.description"
                model-type="App\Models\Brand"
                collection="description_images"
                :sticky="false"
                min-height="200px"
                placeholder="Write brand description..."
              />
            </div>

            <!-- Custom Fields -->
            <template v-if="customFieldDefinitions?.length">
              <div v-for="field in customFieldDefinitions" :key="field.id" class="space-y-2">
                <Label :for="`cf_${field.key}`">
                  {{ field.label }}
                  <span v-if="field.is_required" class="text-destructive">*</span>
                </Label>

                <Input
                  v-if="field.type === 'text'"
                  :id="`cf_${field.key}`"
                  v-model="customFields[field.key]"
                />
                <Input
                  v-else-if="field.type === 'number'"
                  :id="`cf_${field.key}`"
                  v-model="customFields[field.key]"
                  type="number"
                />
                <Textarea
                  v-else-if="field.type === 'textarea'"
                  :id="`cf_${field.key}`"
                  v-model="customFields[field.key]"
                  rows="3"
                />
                <Select v-else-if="field.type === 'select'" v-model="customFields[field.key]">
                  <SelectTrigger :id="`cf_${field.key}`" class="w-full">
                    <SelectValue placeholder="Select..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in field.options" :key="option" :value="option">
                      {{ option }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <Select v-else-if="field.type === 'year_select'" v-model="customFields[field.key]">
                  <SelectTrigger :id="`cf_${field.key}`" class="w-full">
                    <SelectValue placeholder="Select year..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="year in yearOptions" :key="year" :value="String(year)">
                      {{ year }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </template>
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
  </div>
</template>

<script setup>
import TipTapEditor from "@/components/TipTapEditor.vue";
import { MultiSelect } from "@/components/ui/multi-select";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";
import { ComboboxInput as ComboboxInputPrimitive } from "reka-ui";
import { toast } from "vue-sonner";

const props = defineProps({
  brandEvent: Object,
  customFieldDefinitions: { type: Array, default: () => [] },
  customFieldValues: { type: Object, default: () => ({}) },
  businessCategoryOptions: { type: Array, default: () => [] },
});
const emit = defineEmits(["refresh"]);

const route = useRoute();
const client = useSanctumClient();
const project = inject("project");

const saving = ref(false);
const { metaSymbol } = useShortcuts();

// Brand form
const logoFiles = ref([]);
const deleteLogo = ref(false);
const brandForm = reactive({
  name: props.brandEvent?.brand?.name || "",
  company_name: props.brandEvent?.brand?.company_name || "",
  company_email: props.brandEvent?.brand?.company_email || "",
  company_phone: props.brandEvent?.brand?.company_phone || "",
  company_address: props.brandEvent?.brand?.company_address || "",
  description: props.brandEvent?.brand?.description || "",
  business_categories: props.brandEvent?.brand?.business_categories || [],
});

const availableCategoryOptions = computed(() =>
  props.businessCategoryOptions.map((name) => ({ value: name, label: name }))
);
const selectedCategoryOptions = computed({
  get() {
    return (brandForm.business_categories || []).map((name) => ({ value: name, label: name }));
  },
  set(options) {
    brandForm.business_categories = options.map((opt) => opt.value);
  },
});

const customFields = reactive({ ...props.customFieldValues });

const currentYear = new Date().getFullYear();
const yearOptions = computed(() => {
  const years = [];
  for (let y = currentYear; y >= 1950; y--) years.push(y);
  return years;
});

// Booth form
const boothForm = reactive({
  booth_number: props.brandEvent?.booth_number || "",
  booth_size: props.brandEvent?.booth_size || null,
  booth_price: props.brandEvent?.booth_price || null,
  booth_type: props.brandEvent?.booth_type || "",
  sales_id: props.brandEvent?.sales?.id || null,
  status: props.brandEvent?.status || "draft",
  notes: props.brandEvent?.notes || "",
});

const members = computed(() => project.value?.members || []);
const salesSearch = ref("");
const selectedSales = computed(
  () => members.value.find((u) => u.id === boothForm.sales_id) || null
);
const filteredMembers = computed(() => {
  const term = salesSearch.value.trim().toLowerCase();
  if (!term) return members.value;
  return members.value.filter((u) => u.name.toLowerCase().includes(term));
});

// Sync forms when brandEvent changes
watch(
  () => props.brandEvent,
  (val) => {
    if (val?.brand) {
      brandForm.name = val.brand.name || "";
      brandForm.company_name = val.brand.company_name || "";
      brandForm.company_email = val.brand.company_email || "";
      brandForm.company_phone = val.brand.company_phone || "";
      brandForm.company_address = val.brand.company_address || "";
      brandForm.description = val.brand.description || "";
      brandForm.business_categories = val.brand.business_categories || [];
    }
    if (val) {
      boothForm.booth_number = val.booth_number || "";
      boothForm.booth_size = val.booth_size || null;
      boothForm.booth_price = val.booth_price || null;
      boothForm.booth_type = val.booth_type || "";
      boothForm.sales_id = val.sales?.id || null;
      boothForm.status = val.status || "draft";
      boothForm.notes = val.notes || "";
    }
  }
);

watch(
  () => props.customFieldValues,
  (val) => {
    if (val) {
      Object.keys(val).forEach((key) => {
        customFields[key] = val[key];
      });
    }
  }
);

const profileUrl = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${route.params.brandSlug}/profile`
);
const boothUrl = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${route.params.brandSlug}`
);

async function handleSubmit() {
  saving.value = true;
  try {
    const brandBody = { ...brandForm };
    if (logoFiles.value?.[0] && typeof logoFiles.value[0] === "string") {
      brandBody.tmp_brand_logo = logoFiles.value[0];
    }
    if (deleteLogo.value) {
      brandBody.delete_brand_logo = true;
    }
    if (props.customFieldDefinitions?.length) {
      brandBody.project_custom_fields = { ...customFields };
    }

    const boothBody = { ...boothForm };
    if (!boothBody.booth_type) boothBody.booth_type = null;
    if (boothBody.booth_size === "" || boothBody.booth_size === null) boothBody.booth_size = null;
    if (boothBody.booth_price === "" || boothBody.booth_price === null)
      boothBody.booth_price = null;

    await Promise.all([
      client(profileUrl.value, { method: "PUT", body: brandBody }),
      client(boothUrl.value, { method: "PUT", body: boothBody }),
    ]);

    logoFiles.value = [];
    deleteLogo.value = false;

    toast.success("Brand details saved");
    emit("refresh");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to save");
  } finally {
    saving.value = false;
  }
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
