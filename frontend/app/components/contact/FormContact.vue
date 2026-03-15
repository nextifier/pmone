<template>
  <form @submit.prevent="save" class="mt-4 space-y-4">
    <div class="space-y-2">
      <Label for="name">Person Name <span class="text-destructive">*</span></Label>
      <Input id="name" v-model="form.name" />
      <p v-if="errors.name" class="text-destructive text-xs tracking-tight">
        {{ errors.name }}
      </p>
    </div>

    <div class="space-y-2">
      <Label for="company_name">Company Name</Label>
      <Input id="company_name" v-model="form.company_name" />
    </div>

    <div class="space-y-2">
      <Label for="job_title">Job Title</Label>
      <Input id="job_title" v-model="form.job_title" />
    </div>

    <!-- Emails -->
    <div class="space-y-2">
      <div class="flex items-end justify-between gap-x-2">
        <Label>Emails</Label>
        <button
          type="button"
          class="hover:text-foreground text-muted-foreground flex items-center gap-x-1 text-sm tracking-tight"
          @click="form.emails.push('')"
        >
          <Icon name="hugeicons:add-01" class="size-3.5" />
          Add Email
        </button>
      </div>
      <div class="space-y-2">
        <div v-for="(email, index) in form.emails" :key="index" class="flex items-center gap-x-2">
          <Input
            v-model="form.emails[index]"
            type="email"
            placeholder="email@example.com"
            class="flex-1"
          />
          <button
            v-if="form.emails.length > 1"
            type="button"
            @click="form.emails.splice(index, 1)"
            class="text-muted-foreground hover:text-destructive shrink-0 rounded-md p-1.5 transition"
          >
            <Icon name="lucide:x" class="size-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Phones -->
    <div class="space-y-2">
      <div class="flex items-end justify-between gap-x-2">
        <Label>Phones</Label>
        <button
          type="button"
          class="text-muted-foreground hover:text-foreground flex items-center gap-x-1 text-sm tracking-tight"
          @click="form.phones.push('')"
        >
          <Icon name="hugeicons:add-01" class="size-3.5" />
          Add Phone
        </button>
      </div>
      <div class="space-y-2">
        <div v-for="(phone, index) in form.phones" :key="index" class="flex items-center gap-x-2">
          <InputPhone v-model="form.phones[index]" class="flex-1" />
          <button
            v-if="form.phones.length > 1"
            type="button"
            @click="form.phones.splice(index, 1)"
            class="text-muted-foreground hover:text-destructive shrink-0 rounded-md p-1.5 transition"
          >
            <Icon name="lucide:x" class="size-4" />
          </button>
        </div>
      </div>
    </div>

    <div class="space-y-2">
      <Label for="website">Website</Label>
      <InputLink v-model="form.website" label="Website" />
    </div>

    <div class="space-y-2">
      <Label>Business Categories</Label>
      <MultiSelect
        v-if="businessCategoryOptions.length"
        v-model="selectedCategoryOptions"
        :options="availableCategoryOptions"
        placeholder="Add category"
        open-on-focus
      />
      <TagsInput v-else v-model="form.business_categories" class="text-sm">
        <TagsInputItem v-for="cat in form.business_categories" :key="cat" :value="cat">
          <TagsInputItemText />
          <TagsInputItemDelete />
        </TagsInputItem>
        <TagsInputInput placeholder="Add category" />
      </TagsInput>
    </div>

    <div class="space-y-2">
      <Label>Tags</Label>
      <TagsInput v-model="form.tags" class="text-sm">
        <TagsInputItem v-for="tag in form.tags" :key="tag" :value="tag">
          <TagsInputItemText />
          <TagsInputItemDelete />
        </TagsInputItem>
        <TagsInputInput placeholder="Add tag" />
      </TagsInput>
    </div>

    <div class="space-y-2">
      <Label>Country</Label>
      <LocationCombobox
        v-model="form.address.country"
        :options="countries"
        :pinned="['Indonesia']"
        placeholder="Select country"
      />
    </div>

    <div v-if="isIndonesia" class="grid grid-cols-2 gap-x-2 gap-y-4">
      <div class="space-y-2">
        <Label>Province</Label>
        <LocationCombobox
          v-model="form.address.province"
          :options="provinceOptions"
          :pinned="['DKI Jakarta']"
          placeholder="Select province"
        />
      </div>
      <div class="space-y-2">
        <Label>City</Label>
        <LocationCombobox
          v-model="form.address.city"
          :options="cityOptions"
          :disabled="!form.address.province"
          placeholder="Select city"
        />
      </div>
    </div>

    <div class="space-y-2">
      <Label for="address_street">Street Address</Label>
      <Textarea id="address_street" v-model="form.address.street" rows="2" />
    </div>

    <div class="space-y-2">
      <Label for="notes">Notes</Label>
      <Textarea id="notes" v-model="form.notes" rows="3" placeholder="Any additional notes" />
    </div>

    <div class="space-y-2">
      <Label>Projects</Label>
      <ProjectMultiSelect
        v-if="projectOptions.length"
        v-model="selectedProjects"
        :projects="projectOptions"
        placeholder="Associate with projects"
        open-on-focus
      />
      <p v-else class="text-muted-foreground text-sm tracking-tight">No projects available</p>
    </div>

    <div class="grid grid-cols-2 gap-x-2 gap-y-4 sm:grid-cols-3">
      <div class="space-y-2">
        <Label for="contact_type">Contact Type</Label>
        <Select v-model="selectedContactType">
          <SelectTrigger id="contact_type" class="w-full">
            <SelectValue placeholder="Select type" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="opt in contactTypeOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="space-y-2">
        <Label for="source">Source</Label>
        <Select v-model="form.source">
          <SelectTrigger id="source" class="w-full">
            <SelectValue placeholder="Select source" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="event">Event</SelectItem>
            <SelectItem value="referral">Referral</SelectItem>
            <SelectItem value="website">Website</SelectItem>
            <SelectItem value="website inquiries">Website Inquiries</SelectItem>
            <SelectItem value="import">Import</SelectItem>
            <SelectItem value="manual">Manual</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="col-span-2 space-y-2 sm:col-span-1">
        <Label for="status">Status</Label>
        <Select v-model="form.status">
          <SelectTrigger id="status" class="w-full">
            <SelectValue placeholder="Select status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="inactive">Inactive</SelectItem>
            <SelectItem value="archived">Archived</SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>

    <div class="flex justify-end gap-2">
      <Button variant="outline" type="button" @click="emit('cancel')">Cancel</Button>
      <Button type="submit" :disabled="saving">
        <Icon v-if="saving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
        {{ submitLabel }}
        <KbdGroup>
          <Kbd>⌘</Kbd>
          <Kbd>S</Kbd>
        </KbdGroup>
      </Button>
    </div>
  </form>
</template>

<script setup>
import { LocationCombobox } from "@/components/ui/location-combobox";
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
import countries from "@/data/countries.json";
import indonesiaCities from "@/data/indonesia-cities.json";
import indonesiaProvinces from "@/data/indonesia-provinces.json";
import { toast } from "vue-sonner";

defineShortcuts({
  meta_s: {
    handler: (e) => {
      e.preventDefault();
      save();
    },
  },
});

const props = defineProps({
  contact: { type: Object, default: null },
  apiUrl: { type: String, required: true },
  method: { type: String, default: "POST" },
  submitLabel: { type: String, default: "Save Contact" },
  businessCategoryOptions: { type: Array, default: () => [] },
  contactTypeOptions: { type: Array, default: () => [] },
  projectOptions: { type: Array, default: () => [] },
});
const emit = defineEmits(["saved", "cancel"]);
const client = useSanctumClient();

const saving = ref(false);
const errors = ref({});

const form = reactive({
  name: props.contact?.name || "",
  job_title: props.contact?.job_title || "",
  emails: props.contact?.emails?.length ? [...props.contact.emails] : [""],
  phones: props.contact?.phones?.length ? [...props.contact.phones] : [""],
  company_name: props.contact?.company_name || "",
  website: props.contact?.website || "",
  address: {
    street: props.contact?.address?.street || "",
    city: props.contact?.address?.city || "",
    province: props.contact?.address?.province || "",
    country: props.contact?.address?.country || "",
  },
  notes: props.contact?.notes || "",
  source: props.contact?.source || "",
  status: props.contact?.status?.value || props.contact?.status || "active",
  contact_types: props.contact?.contact_types || [],
  business_categories: props.contact?.business_categories || [],
  tags: props.contact?.tags || [],
  project_ids: props.contact?.projects?.map((p) => p.id) || [],
});

// Select: contact type (single)
const selectedContactType = computed({
  get() {
    return form.contact_types?.[0] || "";
  },
  set(val) {
    form.contact_types = val ? [val] : [];
  },
});

// MultiSelect: business category options
const availableCategoryOptions = computed(() =>
  props.businessCategoryOptions.map((name) => ({ value: name, label: name }))
);

const selectedCategoryOptions = computed({
  get() {
    return (form.business_categories || []).map((name) => ({ value: name, label: name }));
  },
  set(options) {
    form.business_categories = options.map((opt) => opt.value);
  },
});

// ProjectMultiSelect: selected projects
const selectedProjects = computed({
  get() {
    return (form.project_ids || []).map((id) => {
      const project = props.projectOptions.find((p) => p.id === id);
      return project || { id, name: `Project #${id}` };
    });
  },
  set(projects) {
    form.project_ids = projects.map((p) => p.id);
  },
});

// Location: cascading dropdowns
const isIndonesia = computed(() => form.address.country === "Indonesia");

const provinceOptions = computed(() => indonesiaProvinces);

const cityOptions = computed(() => {
  const prov = indonesiaProvinces.find((p) => p.label === form.address.province);
  if (!prov) return [];
  return indonesiaCities.filter((c) => c.province === prov.value);
});

// Guard to prevent cascading reset during programmatic form sync (edit mode)
let skipCascadeReset = false;

// Reset cascading fields when country changes
watch(
  () => form.address.country,
  (_, oldVal) => {
    if (skipCascadeReset) return;
    if (oldVal) {
      form.address.province = "";
      form.address.city = "";
    }
  }
);

// Reset city when province changes
watch(
  () => form.address.province,
  (_, oldVal) => {
    if (skipCascadeReset) return;
    if (oldVal) {
      form.address.city = "";
    }
  }
);

// Sync form when contact prop changes
watch(
  () => props.contact,
  (newContact) => {
    if (newContact) {
      skipCascadeReset = true;
      form.name = newContact.name || "";
      form.job_title = newContact.job_title || "";
      form.emails = newContact.emails?.length ? [...newContact.emails] : [""];
      form.phones = newContact.phones?.length ? [...newContact.phones] : [""];
      form.company_name = newContact.company_name || "";
      form.website = newContact.website || "";
      form.address = {
        street: newContact.address?.street || "",
        city: newContact.address?.city || "",
        province: newContact.address?.province || "",
        country: newContact.address?.country || "",
      };
      form.notes = newContact.notes || "";
      form.source = newContact.source || "";
      form.status = newContact.status?.value || newContact.status || "active";
      form.contact_types = newContact.contact_types || [];
      form.business_categories = newContact.business_categories || [];
      form.tags = newContact.tags || [];
      form.project_ids = newContact.projects?.map((p) => p.id) || [];
      nextTick(() => {
        skipCascadeReset = false;
      });
    }
  }
);

async function save() {
  saving.value = true;
  errors.value = {};

  try {
    const body = { ...form };

    // Filter out empty emails and phones
    body.emails = (body.emails || []).filter((e) => e.trim());
    body.phones = (body.phones || []).filter((p) => p.trim());

    // Clean address - send null if all empty
    const addr = body.address;
    if (!addr.street && !addr.city && !addr.province && !addr.country) {
      body.address = null;
    }

    // Clean empty arrays
    if (!body.emails.length) body.emails = null;
    if (!body.phones.length) body.phones = null;
    if (!body.source) body.source = null;

    const response = await client(props.apiUrl, { method: props.method, body });
    toast.success(response.message || "Contact saved successfully");
    emit("saved", response.data);
  } catch (e) {
    if (e?.data?.errors) {
      errors.value = Object.fromEntries(
        Object.entries(e.data.errors).map(([key, val]) => [key, Array.isArray(val) ? val[0] : val])
      );
    }
    toast.error(e?.data?.message || "Failed to save contact");
  } finally {
    saving.value = false;
  }
}
</script>
