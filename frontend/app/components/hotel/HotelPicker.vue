<template>
  <DialogResponsive v-model:open="isOpen" dialog-max-width="28rem" :overflow-content="true">
    <div class="px-4 pb-10 md:px-6 md:py-5">
      <div class="space-y-1">
        <h3 class="page-title">Attach hotel to event</h3>
        <p class="page-description">
          Pick an existing hotel from the global master. You can also create a new one if it
          does not exist yet.
        </p>
      </div>

      <form @submit.prevent="submit" class="mt-4 space-y-4">
        <div class="space-y-2">
          <Label>Hotel</Label>
          <AutocompleteRoot v-model="searchTerm" :ignore-filter="true">
            <AutocompleteAnchor as-child>
              <AutocompleteInput
                placeholder="Type to search hotels"
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
                <AutocompleteViewport class="max-h-64 p-1">
                  <AutocompleteItem
                    v-for="hotel in hotelResults"
                    :key="hotel.id"
                    :value="hotel.name"
                    class="data-[highlighted]:bg-muted flex w-full cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-left outline-none select-none"
                    @select="selectedHotel = hotel"
                  >
                    <img
                      v-if="hotel.featured?.thumbnail || hotel.featured?.sm"
                      :src="hotel.featured.thumbnail || hotel.featured.sm"
                      class="size-8 rounded object-cover"
                      alt=""
                    />
                    <div
                      v-else
                      class="bg-muted text-muted-foreground flex size-8 items-center justify-center rounded"
                    >
                      <Icon name="hugeicons:hotel-01" class="size-4" />
                    </div>
                    <div class="flex min-w-0 flex-col">
                      <span class="truncate text-sm tracking-tight">{{ hotel.name }}</span>
                      <span
                        v-if="hotel.city"
                        class="text-muted-foreground truncate text-xs tracking-tight"
                      >
                        {{ hotel.city
                        }}<template v-if="hotel.country">, {{ hotel.country }}</template>
                      </span>
                    </div>
                    <Icon
                      v-if="selectedHotel?.id === hotel.id"
                      name="lucide:check"
                      class="ml-auto size-4"
                    />
                  </AutocompleteItem>
                  <AutocompleteEmpty
                    v-if="searchTerm.trim() && !pendingSearch"
                    class="text-muted-foreground px-2 py-4 text-center text-sm tracking-tight"
                  >
                    No hotels found. Use "Create new hotel" instead.
                  </AutocompleteEmpty>
                  <div
                    v-if="pendingSearch"
                    class="text-muted-foreground flex items-center justify-center gap-2 px-2 py-3 text-sm tracking-tight"
                  >
                    <Spinner class="size-3.5" />
                    <span>Searching...</span>
                  </div>
                </AutocompleteViewport>
              </AutocompleteContent>
            </AutocompletePortal>
          </AutocompleteRoot>
          <p v-if="errors.hotel_id" class="text-destructive text-xs">{{ errors.hotel_id }}</p>
        </div>

        <div class="space-y-2">
          <Label>Notes</Label>
          <Textarea
            v-model="form.notes"
            placeholder="Internal notes about this hotel for this event (optional)"
            rows="2"
          />
        </div>

        <div class="flex items-center gap-x-2">
          <Switch id="hotel-picker-active" v-model="form.is_active" />
          <Label for="hotel-picker-active" class="text-sm font-normal tracking-tight">
            Active for this event
          </Label>
        </div>

        <div class="flex flex-wrap justify-between gap-2">
          <NuxtLink
            to="/hotels-master/create"
            class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-3 py-2 text-sm tracking-tight active:scale-98"
            @click="isOpen = false"
          >
            <Icon name="lucide:plus" class="size-4 shrink-0" />
            Create new hotel
          </NuxtLink>

          <div class="flex gap-2">
            <Button variant="outline" type="button" @click="isOpen = false">Cancel</Button>
            <Button type="submit" :disabled="submitting || !selectedHotel">
              <Spinner v-if="submitting" class="size-4" />
              <span>Attach</span>
            </Button>
          </div>
        </div>
      </form>
    </div>
  </DialogResponsive>
</template>

<script setup>
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { Textarea } from "@/components/ui/textarea";
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
  eventId: { type: Number, required: true },
});
const emit = defineEmits(["success"]);
const isOpen = defineModel("open", { type: Boolean, default: false });

const client = useSanctumClient();

const submitting = ref(false);
const pendingSearch = ref(false);
const errors = ref({});
const form = reactive({
  notes: "",
  is_active: true,
});

const selectedHotel = ref(null);
const searchTerm = ref("");
const hotelResults = ref([]);

let searchTimer = null;
let searchSeq = 0;

async function runSearch(term) {
  const seq = ++searchSeq;
  if (!term?.trim()) {
    hotelResults.value = [];
    return;
  }
  pendingSearch.value = true;
  try {
    const res = await client("/api/hotels", {
      params: { filter_search: term.trim(), per_page: 20 },
    });
    if (seq !== searchSeq) return;
    hotelResults.value = res.data || [];
  } catch {
    if (seq === searchSeq) hotelResults.value = [];
  } finally {
    if (seq === searchSeq) pendingSearch.value = false;
  }
}

watch(searchTerm, (val) => {
  if (selectedHotel.value && val !== selectedHotel.value.name) {
    selectedHotel.value = null;
  }
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => runSearch(val), 200);
});

watch(isOpen, (val) => {
  if (!val) {
    selectedHotel.value = null;
    searchTerm.value = "";
    hotelResults.value = [];
    form.notes = "";
    form.is_active = true;
    errors.value = {};
    clearTimeout(searchTimer);
  }
});

async function submit() {
  if (!selectedHotel.value) {
    errors.value = { hotel_id: "Pick a hotel first" };
    return;
  }
  submitting.value = true;
  errors.value = {};
  try {
    await client(`/api/events/${props.eventId}/hotels`, {
      method: "POST",
      body: {
        hotel_id: selectedHotel.value.id,
        pivot: {
          is_active: form.is_active,
          notes: form.notes || null,
        },
      },
    });
    toast.success("Hotel attached to event");
    isOpen.value = false;
    emit("success");
  } catch (e) {
    if (e?.data?.errors) {
      errors.value = e.data.errors;
    }
    toast.error(e?.data?.message || "Failed to attach hotel");
  } finally {
    submitting.value = false;
  }
}
</script>
