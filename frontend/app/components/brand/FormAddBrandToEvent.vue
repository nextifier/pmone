<template>
  <Dialog v-model:open="isOpen">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle class="page-title">Add Brand to Event</DialogTitle>
        <DialogDescription class="page-description -mt-1"
          >Create or attach an existing brand to this event.</DialogDescription
        >
      </DialogHeader>

      <form @submit.prevent="submit" class="space-y-4">
        <div class="space-y-2">
          <Label>Brand Name<span class="text-destructive">*</span></Label>
          <div class="relative">
            <Input
              ref="brandInputRef"
              v-model="searchTerm"
              placeholder="Type to search brands..."
              autocomplete="off"
              @focus="showDropdown = true"
              @blur="hideDropdown"
              @keydown="handleInputKeydown"
            />
            <div
              v-if="showDropdown && brandResults.length"
              class="bg-popover text-popover-foreground absolute top-full left-0 z-50 mt-1 w-full overflow-hidden rounded-md border shadow-md"
            >
              <div class="max-h-48 overflow-y-auto p-1">
                <button
                  v-for="(brand, idx) in brandResults"
                  :key="brand.id"
                  type="button"
                  :class="[
                    'flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left',
                    idx === highlightedIndex ? 'bg-muted' : 'hover:bg-muted',
                  ]"
                  @mousedown.prevent="selectBrand(brand)"
                  @mouseenter="highlightedIndex = idx"
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
                </button>
              </div>
            </div>
          </div>
          <p v-if="errors.brand_name" class="text-destructive text-xs">{{ errors.brand_name }}</p>
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
    </DialogContent>
  </Dialog>
</template>

<script setup>
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
  booth_size: null,
  booth_price: null,
  sales_id: null,
  emails: [""],
  send_login_email: false,
});

const brandInputRef = ref(null);
const selectedBrand = ref(null);
const showDropdown = ref(false);
const highlightedIndex = ref(-1);
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

function selectBrand(brand) {
  selectedBrand.value = brand;
  searchTerm.value = brand.name;
  showDropdown.value = false;
  highlightedIndex.value = -1;
}

function hideDropdown() {
  setTimeout(() => {
    showDropdown.value = false;
    highlightedIndex.value = -1;
  }, 150);
}

function handleInputKeydown(e) {
  const results = brandResults.value;
  const dropdownVisible = showDropdown.value && results.length > 0;

  if (e.key === "ArrowDown") {
    e.preventDefault();
    if (!dropdownVisible) {
      showDropdown.value = true;
      highlightedIndex.value = 0;
    } else {
      highlightedIndex.value = (highlightedIndex.value + 1) % results.length;
    }
  } else if (e.key === "ArrowUp") {
    e.preventDefault();
    if (dropdownVisible) {
      highlightedIndex.value =
        highlightedIndex.value <= 0 ? results.length - 1 : highlightedIndex.value - 1;
    }
  } else if (e.key === "Enter") {
    if (dropdownVisible && highlightedIndex.value >= 0) {
      e.preventDefault();
      selectBrand(results[highlightedIndex.value]);
    }
  } else if (e.key === "Tab") {
    if (dropdownVisible && highlightedIndex.value >= 0) {
      selectBrand(results[highlightedIndex.value]);
    } else {
      showDropdown.value = false;
    }
  } else if (e.key === "Escape") {
    showDropdown.value = false;
    highlightedIndex.value = -1;
  }
}

watch(searchTerm, (val) => {
  if (selectedBrand.value && val !== selectedBrand.value.name) {
    selectedBrand.value = null;
  }
  if (val.trim()) {
    showDropdown.value = true;
    highlightedIndex.value = -1;
  }
});

watch(isOpen, (val) => {
  if (val) {
    fetchBrands();
    nextTick(() => {
      const el = brandInputRef.value?.$el || brandInputRef.value;
      el?.focus?.();
    });
  } else {
    selectedBrand.value = null;
    showDropdown.value = false;
    highlightedIndex.value = -1;
    searchTerm.value = "";
    form.booth_size = null;
    form.booth_price = null;
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
        booth_size: form.booth_size || null,
        booth_price: form.booth_price || null,
        sales_id: form.sales_id || null,
        emails,
        send_login_email: form.send_login_email,
      },
    });
    toast.success("Brand added to event");
    selectedBrand.value = null;
    searchTerm.value = "";
    form.booth_size = null;
    form.booth_price = null;
    form.sales_id = null;
    form.emails = [""];
    form.send_login_email = false;
    brandsLoaded.value = false;
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
