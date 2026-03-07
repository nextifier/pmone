<template>
  <DialogResponsive v-model:open="isOpen" dialog-max-width="28rem" :overflow-content="true">
    <div class="px-4 pb-10 md:px-6 md:py-5">
      <div class="space-y-1">
        <h3 class="page-title">Add Brand to Event</h3>
        <p class="page-description">Create or attach an existing brand to this event.</p>
      </div>

      <form @submit.prevent="submit" class="mt-4 space-y-4">
        <div class="space-y-2">
          <Label>Brand Name<span class="text-destructive">*</span></Label>
          <AutocompleteRoot v-model="searchTerm" :ignore-filter="true">
            <AutocompleteAnchor as-child>
              <AutocompleteInput
                placeholder="Type to search brands..."
                autocomplete="off"
                class="placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-background border-border focus-visible:border-ring focus-visible:ring-ring flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-sm tracking-tight shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[1px]"
                auto-focus
              />
            </AutocompleteAnchor>
            <AutocompletePortal>
              <AutocompleteContent
                position="popper"
                :side-offset="4"
                hide-when-empty
                class="bg-popover text-popover-foreground z-[100] w-[var(--reka-combobox-trigger-width)] overflow-hidden rounded-md border shadow-md"
              >
                <AutocompleteViewport class="max-h-48 p-1">
                  <AutocompleteItem
                    v-for="brand in brandResults"
                    :key="brand.id"
                    :value="brand.name"
                    class="data-[highlighted]:bg-muted flex w-full cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-left outline-none select-none"
                    @select="selectedBrand = brand"
                  >
                    <img
                      v-if="brand.brand_logo"
                      :src="brand.brand_logo"
                      class="size-6 rounded object-cover"
                      alt=""
                    />
                    <div
                      v-else
                      class="text-muted-foreground flex size-6 items-center justify-center rounded text-xs font-medium"
                    >
                      {{ brand.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="flex flex-col">
                      <span class="text-sm">{{ brand.name }}</span>
                      <span v-if="brand.company_name" class="text-muted-foreground text-xs">{{
                        brand.company_name
                      }}</span>
                    </div>
                    <Icon
                      v-if="selectedBrand?.id === brand.id"
                      name="lucide:check"
                      class="ml-auto size-4"
                    />
                  </AutocompleteItem>
                  <AutocompleteEmpty
                    v-if="searchTerm.trim()"
                    class="text-muted-foreground px-2 py-4 text-center text-sm"
                  >
                    No brands found
                  </AutocompleteEmpty>
                </AutocompleteViewport>
              </AutocompleteContent>
            </AutocompletePortal>
          </AutocompleteRoot>
          <p v-if="errors.brand_name" class="text-destructive text-xs">{{ errors.brand_name }}</p>
        </div>

        <!-- Booth Number(s) -->
        <div class="space-y-2">
          <Label>Booth Number(s)</Label>
          <Input v-model="form.booth_number" placeholder="e.g. A01, A02, A03" />
          <p class="text-muted-foreground text-xs">
            Comma-separated booth numbers. Each booth number creates a separate brand-event record.
          </p>
        </div>

        <div class="grid grid-cols-2 gap-x-2 gap-y-4">
          <div class="space-y-2">
            <Label>Booth Size (m²)</Label>
            <Input
              v-model.number="form.booth_size"
              type="number"
              min="0"
              step="0.01"
              placeholder="e.g. 36"
            />
          </div>
          <div class="space-y-2">
            <Label>Booth Price (Rp)</Label>
            <Input
              v-model.number="form.booth_price"
              type="number"
              min="0"
              placeholder="e.g. 50000000"
            />
          </div>
        </div>

        <!-- Fascia Name & Badge Name -->
        <div class="grid grid-cols-2 gap-x-2 gap-y-4">
          <div class="space-y-2">
            <Label>Fascia Name</Label>
            <Input v-model="form.fascia_name" placeholder="Name on booth fascia" />
            <p class="text-muted-foreground text-xs">Displayed on the booth signage.</p>
          </div>
          <div class="space-y-2">
            <Label>Badge Name</Label>
            <Input v-model="form.badge_name" placeholder="Name on exhibitor badge" />
            <p class="text-muted-foreground text-xs">Printed on exhibitor badges.</p>
          </div>
        </div>

        <div class="space-y-2">
          <Label>Sales</Label>
          <Select v-model="form.sales_id">
            <SelectTrigger class="w-full">
              <template #default>
                <div v-if="selectedSales" class="flex items-center gap-2">
                  <Avatar :model="selectedSales" size="sm" class="size-5" />
                  <span class="truncate">{{ selectedSales.name }}</span>
                </div>
                <span v-else class="text-muted-foreground">Select sales person</span>
              </template>
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="null">None</SelectItem>
              <SelectItem v-for="user in members" :key="user.id" :value="user.id">
                <div class="flex items-center gap-2">
                  <Avatar :model="user" size="sm" class="size-5" />
                  <span>{{ user.name }}</span>
                </div>
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-2">
          <Label>PIC Email(s)</Label>
          <div class="space-y-2">
            <div v-for="(email, i) in form.emails" :key="i" class="flex gap-2">
              <Input
                v-model="form.emails[i]"
                type="email"
                placeholder="email@example.com"
                class="flex-1"
              />
              <Button
                v-if="form.emails.length > 1"
                variant="ghost"
                size="sm"
                @click="form.emails.splice(i, 1)"
                type="button"
              >
                <Icon name="hugeicons:delete-02" class="size-4" />
              </Button>
            </div>
            <Button variant="outline" size="sm" type="button" @click="form.emails.push('')">
              <Icon name="hugeicons:add-01" class="mr-1 size-4" />
              Add Email
            </Button>
          </div>
        </div>

        <div class="flex items-center gap-x-2">
          <Checkbox id="send_login" v-model="form.send_login_email" />
          <Label for="send_login" class="text-sm font-normal">Send login email to PIC(s)</Label>
        </div>

        <div class="flex justify-end gap-2">
          <Button variant="outline" type="button" @click="isOpen = false">Cancel</Button>
          <Button type="submit" :disabled="submitting">
            <Icon v-if="submitting" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
            Add Brand
          </Button>
        </div>
      </form>
    </div>
  </DialogResponsive>
</template>

<script setup>
import {
  AutocompleteAnchor,
  AutocompleteContent,
  AutocompleteEmpty,
  AutocompleteInput,
  AutocompleteItem,
  AutocompletePortal,
  AutocompleteRoot,
  AutocompleteViewport,
} from "reka-ui";
import { toast } from "vue-sonner";

const props = defineProps({
  username: { type: String, required: true },
  eventSlug: { type: String, required: true },
  members: { type: Array, default: () => [] },
});
const emit = defineEmits(["success"]);
const isOpen = defineModel("open", { type: Boolean, default: false });

const client = useSanctumClient();

const submitting = ref(false);
const errors = ref({});
const form = reactive({
  booth_number: "",
  booth_size: null,
  booth_price: null,
  fascia_name: "",
  badge_name: "",
  sales_id: null,
  emails: [""],
  send_login_email: false,
});

const selectedBrand = ref(null);
const searchTerm = ref("");
const allBrands = ref([]);
const brandsLoaded = ref(false);

const brandResults = computed(() => {
  const term = searchTerm.value.trim().toLowerCase();
  if (!term) return [];
  return allBrands.value.filter(
    (b) =>
      b.name.toLowerCase().includes(term) ||
      (b.company_name && b.company_name.toLowerCase().includes(term))
  );
});

const selectedSales = computed(() => props.members.find((u) => u.id === form.sales_id) || null);

async function fetchBrands() {
  if (brandsLoaded.value) return;
  try {
    const res = await client("/api/brands/search", { params: { q: "*" } });
    allBrands.value = res.data || [];
    brandsLoaded.value = true;
  } catch {
    allBrands.value = [];
  }
}

watch(searchTerm, (val) => {
  if (selectedBrand.value && val !== selectedBrand.value.name) {
    selectedBrand.value = null;
  }
});

watch(isOpen, (val) => {
  if (val) {
    fetchBrands();
  } else {
    selectedBrand.value = null;
    searchTerm.value = "";
    form.booth_number = "";
    form.booth_size = null;
    form.booth_price = null;
    form.fascia_name = "";
    form.badge_name = "";
    form.sales_id = null;
    form.emails = [""];
    form.send_login_email = false;
    errors.value = {};
    brandsLoaded.value = false;
  }
});

async function submit() {
  const brandName = selectedBrand.value?.name || searchTerm.value.trim();
  if (!brandName) {
    errors.value = { brand_name: "Brand name is required" };
    return;
  }

  submitting.value = true;
  errors.value = {};
  try {
    const emails = form.emails.filter((e) => e.trim());
    await client(`/api/projects/${props.username}/events/${props.eventSlug}/brands`, {
      method: "POST",
      body: {
        brand_name: brandName,
        booth_number: form.booth_number || null,
        booth_size: form.booth_size || null,
        booth_price: form.booth_price || null,
        fascia_name: form.fascia_name || null,
        badge_name: form.badge_name || null,
        sales_id: form.sales_id || null,
        emails,
        send_login_email: form.send_login_email,
      },
    });
    toast.success("Brand added to event");
    isOpen.value = false;
    emit("success");
  } catch (e) {
    if (e?.data?.errors) {
      errors.value = e.data.errors;
    }
    toast.error(e?.data?.message || "Failed to add brand");
  } finally {
    submitting.value = false;
  }
}
</script>
