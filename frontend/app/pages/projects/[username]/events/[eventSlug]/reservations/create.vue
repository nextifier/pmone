<template>
  <div class="space-y-6 pb-16">
    <div class="flex flex-col items-start gap-y-4">
      <ButtonBack :destination="`${eventBase}/reservations`" />
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:calendar-add-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Create Manual Reservation</h1>
      </div>
    </div>

    <form @submit.prevent="handleSubmit" class="space-y-8">
      <section class="space-y-3 rounded-md border p-4">
        <div>
          <h2 class="text-base font-semibold tracking-tight">Hotel</h2>
          <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
            Select the hotel for this booking. Room types will be loaded after.
          </p>
        </div>

        <div class="space-y-2">
          <Label>Hotel</Label>
          <Select
            :model-value="form.hotel_id ? String(form.hotel_id) : undefined"
            @update:model-value="handleHotelChange"
          >
            <SelectTrigger class="w-full">
              <SelectValue placeholder="Select a hotel" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="h in hotels" :key="h.id" :value="String(h.id)">
                {{ h.name }}<span v-if="h.city" class="text-muted-foreground"> · {{ h.city }}</span>
              </SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.hotel_id" class="text-destructive text-xs sm:text-sm">{{ errors.hotel_id[0] }}</p>
        </div>
      </section>

      <section class="space-y-3 rounded-md border p-4">
        <div>
          <h2 class="text-base font-semibold tracking-tight">Guest Information</h2>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div class="space-y-2">
            <Label>Name</Label>
            <Input v-model="form.guest_name" required />
            <p v-if="errors.guest_name" class="text-destructive text-xs sm:text-sm">{{ errors.guest_name[0] }}</p>
          </div>
          <div class="space-y-2">
            <Label>Email</Label>
            <Input v-model="form.guest_email" type="email" required />
            <p v-if="errors.guest_email" class="text-destructive text-xs sm:text-sm">{{ errors.guest_email[0] }}</p>
          </div>
          <div class="space-y-2">
            <Label>Phone</Label>
            <Input v-model="form.guest_phone" required />
            <p v-if="errors.guest_phone" class="text-destructive text-xs sm:text-sm">{{ errors.guest_phone[0] }}</p>
          </div>
          <div class="grid grid-cols-3 gap-2">
            <div class="space-y-2 col-span-1">
              <Label>ID Type</Label>
              <Select v-model="form.guest_identity_type">
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="nik">NIK</SelectItem>
                  <SelectItem value="passport">Passport</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2 col-span-2">
              <Label>ID Number</Label>
              <Input v-model="form.guest_identity_number" required />
              <p v-if="errors.guest_identity_number" class="text-destructive text-xs sm:text-sm">{{ errors.guest_identity_number[0] }}</p>
            </div>
          </div>
          <div class="space-y-2">
            <Label>Nationality (optional)</Label>
            <Input v-model="form.guest_nationality" />
          </div>
          <div class="space-y-2">
            <Label>Company (optional)</Label>
            <Input v-model="form.guest_company" />
          </div>
        </div>

        <div class="space-y-2">
          <Label>Special Request (optional)</Label>
          <Textarea v-model="form.special_request" rows="2" />
        </div>
      </section>

      <section class="space-y-3 rounded-md border p-4">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-base font-semibold tracking-tight">Rooms</h2>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">At least one room item is required.</p>
          </div>
          <Button type="button" size="sm" variant="outline" @click="addItem" :disabled="!form.hotel_id">
            <Icon name="lucide:plus" class="-ml-1 size-4" /> Add Room
          </Button>
        </div>

        <div v-if="!form.items.length" class="text-muted-foreground rounded-md border border-dashed py-6 text-center text-sm tracking-tight">
          {{ form.hotel_id ? 'Click "Add Room" to add a room item.' : "Select a hotel first." }}
        </div>

        <div v-for="(item, idx) in form.items" :key="idx" class="rounded-md border bg-muted/30 p-3 space-y-3">
          <div class="flex items-start justify-between gap-2">
            <div class="text-sm font-medium tracking-tight">Room #{{ idx + 1 }}</div>
            <button type="button" class="text-destructive hover:bg-destructive/10 inline-flex size-7 items-center justify-center rounded" @click="removeItem(idx)" title="Remove">
              <Icon name="lucide:trash" class="size-3.5" />
            </button>
          </div>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="space-y-2 sm:col-span-2">
              <Label>Room Type</Label>
              <Select :model-value="item.room_type_id ? String(item.room_type_id) : undefined" @update:model-value="(v) => (item.room_type_id = v ? Number(v) : null)">
                <SelectTrigger class="w-full">
                  <SelectValue placeholder="Select room type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="r in roomTypes" :key="r.id" :value="String(r.id)">
                    {{ r.name }} <span class="text-muted-foreground">· Rp{{ formatRupiah(r.base_rate) }}/night · max {{ r.max_pax }} pax</span>
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="errors[`items.${idx}.room_type_id`]" class="text-destructive text-xs sm:text-sm">{{ errors[`items.${idx}.room_type_id`][0] }}</p>
            </div>
            <div class="space-y-2">
              <Label>Check-in</Label>
              <DatePicker
                :model-value="parseLocalDateString(item.check_in_date)"
                placeholder="Pick check-in date"
                @update:model-value="(d) => (item.check_in_date = d ? toLocalDateString(d) : '')"
              />
              <p v-if="errors[`items.${idx}.check_in_date`]" class="text-destructive text-xs sm:text-sm">{{ errors[`items.${idx}.check_in_date`][0] }}</p>
            </div>
            <div class="space-y-2">
              <Label>Check-out</Label>
              <DatePicker
                :model-value="parseLocalDateString(item.check_out_date)"
                placeholder="Pick check-out date"
                :min="parseLocalDateString(item.check_in_date)"
                @update:model-value="(d) => (item.check_out_date = d ? toLocalDateString(d) : '')"
              />
              <p v-if="errors[`items.${idx}.check_out_date`]" class="text-destructive text-xs sm:text-sm">{{ errors[`items.${idx}.check_out_date`][0] }}</p>
            </div>
            <div class="space-y-2">
              <Label>Quantity</Label>
              <InputNumber v-model="item.qty" :min="1" :max="20" required />
            </div>
            <div class="space-y-2">
              <Label>Guest Name on Room (optional)</Label>
              <Input v-model="item.guest_name" placeholder="Leave blank to use main guest" />
            </div>
            <div class="space-y-2 sm:col-span-2">
              <Label>Notes (optional)</Label>
              <Textarea
                v-model="item.notes"
                rows="2"
                maxlength="1000"
                placeholder="e.g. extra bed, high floor, late check-in..."
              />
              <p v-if="errors[`items.${idx}.notes`]" class="text-destructive text-xs sm:text-sm">
                {{ errors[`items.${idx}.notes`][0] }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <section class="space-y-3 rounded-md border p-4">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-base font-semibold tracking-tight">Transfers (optional)</h2>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">Add airport transfers if applicable.</p>
          </div>
          <Button type="button" size="sm" variant="outline" @click="addTransfer" :disabled="!form.hotel_id || !transferOptions.length">
            <Icon name="lucide:plus" class="-ml-1 size-4" /> Add Transfer
          </Button>
        </div>

        <div v-for="(t, idx) in form.transfers" :key="idx" class="rounded-md border bg-muted/30 p-3 space-y-3">
          <div class="flex items-start justify-between gap-2">
            <div class="text-sm font-medium tracking-tight">Transfer #{{ idx + 1 }}</div>
            <button type="button" class="text-destructive hover:bg-destructive/10 inline-flex size-7 items-center justify-center rounded" @click="removeTransfer(idx)">
              <Icon name="lucide:trash" class="size-3.5" />
            </button>
          </div>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="space-y-2 sm:col-span-2">
              <Label>Transfer Option</Label>
              <Select :model-value="t.transfer_option_id ? String(t.transfer_option_id) : undefined" @update:model-value="(v) => updateTransferOption(idx, v)">
                <SelectTrigger class="w-full"><SelectValue placeholder="Select transfer" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="opt in transferOptions" :key="opt.id" :value="String(opt.id)">
                    {{ opt.label }} <span class="text-muted-foreground">· {{ opt.direction_label || opt.direction }} · Rp{{ formatRupiah(opt.price) }}</span>
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2">
              <Label>Direction</Label>
              <Select v-model="t.direction">
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="in">Arrival</SelectItem>
                  <SelectItem value="out">Departure</SelectItem>
                  <SelectItem value="both">Round-trip</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2">
              <Label>Date</Label>
              <DatePicker
                :model-value="parseLocalDateString(t.transfer_date)"
                placeholder="Pick transfer date"
                @update:model-value="(d) => (t.transfer_date = d ? toLocalDateString(d) : '')"
              />
            </div>
            <div class="space-y-2">
              <Label>Pax</Label>
              <InputNumber v-model="t.pax_count" :min="1" required />
            </div>
            <div class="space-y-2">
              <Label>Price (IDR)</Label>
              <InputGroup>
                <InputNumber
                  v-model="t.price"
                  :min="0"
                  required
                  data-slot="input-group-control"
                  class="flex-1 rounded-none border-0 shadow-none focus-visible:ring-0 focus-visible:ring-transparent dark:bg-transparent"
                />
                <InputGroupAddon>
                  <InputGroupText>Rp</InputGroupText>
                </InputGroupAddon>
              </InputGroup>
            </div>
            <div class="space-y-2 sm:col-span-2">
              <Label>Notes (optional)</Label>
              <Textarea
                v-model="t.note"
                rows="2"
                maxlength="1000"
                placeholder="e.g. flight number, pickup time, contact name..."
              />
              <p
                v-if="errors[`transfers.${idx}.note`]"
                class="text-destructive text-xs sm:text-sm"
              >
                {{ errors[`transfers.${idx}.note`][0] }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <section class="space-y-3 rounded-md border p-4">
        <div>
          <h2 class="text-base font-semibold tracking-tight">Payment</h2>
        </div>

        <div class="space-y-2">
          <Label>Payment Mode</Label>
          <Select v-model="form.payment_mode">
            <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="xendit">Generate Xendit Invoice (send to guest)</SelectItem>
              <SelectItem value="manual_paid">Mark as Paid (manual bank transfer)</SelectItem>
              <SelectItem value="skip">Complimentary (skip payment)</SelectItem>
            </SelectContent>
          </Select>
          <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
            <span v-if="form.payment_mode === 'xendit'">Guest will receive a payment link via email.</span>
            <span v-else-if="form.payment_mode === 'manual_paid'">Reservation will be marked as paid immediately.</span>
            <span v-else>Reservation will be marked as paid (complimentary, no payment).</span>
          </p>
        </div>

        <Alert
          v-if="form.payment_mode === 'xendit' && project && project.has_xendit_gateway === false"
          class="border-warning/40 bg-warning/10"
        >
          <Icon name="hugeicons:alert-02" class="text-warning-foreground" />
          <AlertTitle class="tracking-tight">Xendit gateway belum dikonfigurasi</AlertTitle>
          <AlertDescription class="tracking-tight">
            Project ini belum punya Xendit gateway aktif untuk mode <span class="font-mono">{{ gatewayMode }}</span>.
            <NuxtLink :to="project.xendit_setup_url" class="underline font-medium">
              Setup di Settings → Payment Gateways
            </NuxtLink>
            atau pilih payment mode lain di atas.
          </AlertDescription>
        </Alert>

        <div class="space-y-2">
          <Label>Internal Notes (optional)</Label>
          <Textarea v-model="form.notes" rows="2" placeholder="Visible to admins only" />
        </div>
      </section>

      <div class="flex flex-wrap gap-2 justify-end">
        <NuxtLink
          :to="`${eventBase}/reservations`"
          class="border-border hover:bg-muted inline-flex items-center rounded-md border px-3 py-1.5 text-sm tracking-tight"
        >
          Cancel
        </NuxtLink>
        <Button type="submit" :disabled="saving || !form.hotel_id || !form.items.length || gatewayBlocked">
          <Spinner v-if="saving" />
          {{ saving ? "Creating..." : "Create Reservation" }}
        </Button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { Textarea } from "@/components/ui/textarea";
import { parseLocalDateString, toLocalDateString } from "@/lib/utils";
import { computed, reactive, ref, watch } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["reservations.manual_entry"],
  layout: "app",
});

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();
const router = useRouter();
const client = useSanctumClient();

const eventBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}`
);

usePageMeta(null, { title: computed(() => `New Reservation · ${props.event?.title || "Event"}`) });

const { data: hotelsData } = await useLazySanctumFetch(
  () => `/api/events/${props.event?.id}/hotels?per_page=200&filter_is_active=1`,
  { key: () => `manual-reservation-hotels-${props.event?.id}` }
);
const hotels = computed(() => hotelsData.value?.data ?? []);

const roomTypes = ref([]);
const transferOptions = ref([]);

const form = reactive({
  hotel_id: null,
  guest_name: "",
  guest_email: "",
  guest_phone: "",
  guest_identity_type: "nik",
  guest_identity_number: "",
  guest_nationality: "",
  guest_company: "",
  special_request: "",
  notes: "",
  items: [],
  transfers: [],
  payment_mode: "xendit",
});

const errors = ref({});
const saving = ref(false);

const handleHotelChange = async (val) => {
  form.hotel_id = val ? Number(val) : null;
  form.items = [];
  form.transfers = [];
  roomTypes.value = [];
  transferOptions.value = [];

  if (!form.hotel_id) return;

  const hotel = hotels.value.find((h) => h.id === form.hotel_id);
  if (!hotel) return;

  try {
    const [rooms, transfers] = await Promise.all([
      client(`/api/events/${props.event.id}/hotels/${hotel.slug}/room-types?per_page=100&filter_is_active=1`),
      client(`/api/events/${props.event.id}/hotels/${hotel.slug}/transfer-options?per_page=100&filter_is_active=1`),
    ]);
    roomTypes.value = rooms?.data ?? [];
    transferOptions.value = transfers?.data ?? [];
  } catch (err) {
    toast.error("Failed to load hotel details", { description: err?.data?.message || err?.message });
  }
};

const addItem = () => {
  form.items.push({
    room_type_id: null,
    check_in_date: "",
    check_out_date: "",
    qty: 1,
    guest_name: "",
    notes: "",
  });
};

const removeItem = (idx) => {
  form.items.splice(idx, 1);
};

const addTransfer = () => {
  form.transfers.push({
    transfer_option_id: null,
    direction: "in",
    transfer_date: "",
    pax_count: 1,
    price: 0,
    note: "",
  });
};

const removeTransfer = (idx) => {
  form.transfers.splice(idx, 1);
};

const updateTransferOption = (idx, val) => {
  const id = val ? Number(val) : null;
  form.transfers[idx].transfer_option_id = id;
  if (id) {
    const opt = transferOptions.value.find((o) => o.id === id);
    if (opt) {
      form.transfers[idx].price = Number(opt.price) || 0;
      if (opt.direction && opt.direction !== "both") {
        form.transfers[idx].direction = opt.direction;
      }
    }
  }
};

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);

const gatewayMode = "test";

const gatewayBlocked = computed(
  () => form.payment_mode === "xendit" && props.project?.has_xendit_gateway === false
);

const handleSubmit = async () => {
  errors.value = {};
  saving.value = true;
  try {
    const payload = {
      hotel_id: form.hotel_id,
      guest_name: form.guest_name,
      guest_email: form.guest_email,
      guest_phone: form.guest_phone,
      guest_identity_type: form.guest_identity_type,
      guest_identity_number: form.guest_identity_number,
      guest_nationality: form.guest_nationality || null,
      guest_company: form.guest_company || null,
      special_request: form.special_request || null,
      notes: form.notes || null,
      items: form.items.map((i) => ({
        room_type_id: i.room_type_id,
        check_in_date: i.check_in_date,
        check_out_date: i.check_out_date,
        qty: Number(i.qty) || 1,
        guest_name: i.guest_name || null,
      })),
      transfers: form.transfers.map((t) => ({
        transfer_option_id: t.transfer_option_id,
        direction: t.direction,
        transfer_date: t.transfer_date,
        pax_count: Number(t.pax_count) || 1,
        price: Number(t.price) || 0,
      })),
      payment_mode: form.payment_mode,
    };

    const response = await client(`/api/events/${props.event.id}/reservations/manual`, {
      method: "POST",
      body: payload,
    });

    toast.success("Reservation created", {
      description: response?.data?.reservation_number,
    });

    const ulid = response?.data?.ulid;
    if (ulid) {
      router.push(`${eventBase.value}/reservations/${ulid}`);
    } else {
      router.push(`${eventBase.value}/reservations`);
    }
  } catch (err) {
    if (err?.status === 422 && err?.data?.errors) {
      errors.value = err.data.errors;
      toast.error("Validation failed", { description: "Please review the form." });
    } else {
      toast.error("Save failed", { description: err?.data?.message || err?.message });
    }
  } finally {
    saving.value = false;
  }
};
</script>
