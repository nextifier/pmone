<template>
  <div class="space-y-4">
    <div class="flex justify-end">
      <Button v-if="canCreate" size="sm" @click="openCreateDialog">
        <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
        Add Allotment
      </Button>
    </div>

    <TableData
      :data="allotments"
      :columns="columns"
      :meta="meta"
      model="allotments"
      label="Allotment"
      :pending="pending"
      :client-only="true"
      :show-add-button="false"
      :column-toggle="false"
      :searchable="false"
      :initial-pagination="{ pageIndex: 0, pageSize: 50 }"
      :initial-sorting="[{ id: 'start_date', desc: true }]"
      @refresh="refresh"
    />

    <DialogResponsive v-model:open="dialogOpen" dialog-max-width="30rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">
            {{ editing ? "Edit Allotment" : "Add Allotment" }}
          </h3>

          <form @submit.prevent="handleSubmit" class="mt-4 space-y-3">
            <div class="space-y-2">
              <Label for="allotment_room_type">Room Type</Label>
              <Select
                :model-value="form.room_type_id ? String(form.room_type_id) : undefined"
                @update:model-value="(v) => (form.room_type_id = v ? Number(v) : null)"
              >
                <SelectTrigger id="allotment_room_type" class="w-full">
                  <SelectValue placeholder="Select room type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="r in roomTypes" :key="r.id" :value="String(r.id)">
                    {{ r.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="grid grid-cols-2 gap-x-2">
              <div class="space-y-2">
                <Label>Date Range</Label>
                <RangeCalendarPicker
                  size="default"
                  placeholder="Pick date range"
                  :model-value="{ start: form._start_date_obj, end: form._end_date_obj }"
                  @update:model-value="onDateRangeUpdate"
                />
              </div>
              <div class="space-y-2">
                <Label>Quantity</Label>
                <InputNumber v-model="form.quantity" :min="1" required />
              </div>
            </div>

            <div class="space-y-2">
              <div class="flex items-center gap-1.5 leading-none">
                <Label>Custom base rate (override)</Label>
                <Tippy>
                  <button
                    type="button"
                    aria-label="More information"
                    class="text-muted-foreground hover:text-foreground inline-flex cursor-help rounded-full align-middle transition-colors"
                  >
                    <Icon name="lucide:info" class="size-4" />
                  </button>
                  <template #content>
                    <span
                      class="block max-w-[20rem] p-2.5 text-left text-sm leading-normal tracking-tight"
                    >
                      When set, this rate replaces the room type's default base rate for this event.
                      Dynamic pricing periods still take precedence when they apply.
                    </span>
                  </template>
                </Tippy>
              </div>
              <InputGroup>
                <InputNumber
                  v-model="form.base_rate_override"
                  :min="0"
                  decimal
                  placeholder="Leave empty to use room type's default base rate"
                  data-slot="input-group-control"
                  class="flex-1 rounded-none border-0 shadow-none focus-visible:ring-0 focus-visible:ring-transparent dark:bg-transparent"
                />
                <InputGroupAddon>
                  <InputGroupText>Rp</InputGroupText>
                </InputGroupAddon>
              </InputGroup>
            </div>

            <div class="grid grid-cols-2 gap-x-2">
              <div class="space-y-2">
                <div class="flex items-center gap-1.5 leading-none">
                  <Label for="surcharge_type">Surcharge Type</Label>
                  <Tippy>
                    <button
                      type="button"
                      aria-label="More information"
                      class="text-muted-foreground hover:text-foreground inline-flex cursor-help rounded-full align-middle transition-colors"
                    >
                      <Icon name="lucide:info" class="size-4" />
                    </button>
                    <template #content>
                      <span
                        class="block max-w-[20rem] p-2.5 text-left text-sm leading-normal tracking-tight"
                      >
                        Optional extra fee added on top of the room rate for this allotment, charged
                        per room per night (e.g. a peak-period or event-week surcharge). Fixed adds
                        a flat IDR amount; Percentage adds a percentage of each night's rate.
                      </span>
                    </template>
                  </Tippy>
                </div>
                <Select
                  :model-value="form.surcharge_type ?? 'none'"
                  @update:model-value="(v) => (form.surcharge_type = v === 'none' ? null : v)"
                >
                  <SelectTrigger id="surcharge_type" class="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">None</SelectItem>
                    <SelectItem value="fixed">Fixed (IDR)</SelectItem>
                    <SelectItem value="percentage">Percentage (%)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>Surcharge Amount</Label>
                <InputGroup>
                  <InputNumber
                    v-model="form.surcharge_amount"
                    :min="0"
                    decimal
                    :disabled="!form.surcharge_type"
                    data-slot="input-group-control"
                    class="flex-1 rounded-none border-0 shadow-none focus-visible:ring-0 focus-visible:ring-transparent dark:bg-transparent"
                  />
                  <InputGroupAddon :align="form.surcharge_type === 'percentage' ? 'inline-end' : 'inline-start'">
                    <InputGroupText>{{ form.surcharge_type === "percentage" ? "%" : "Rp" }}</InputGroupText>
                  </InputGroupAddon>
                </InputGroup>
              </div>
            </div>

            <div class="space-y-2">
              <Label>Release At (auto-release unsold)</Label>
              <DatePicker
                with-time
                :model-value="form._release_at_obj"
                placeholder="Optional release datetime"
                @update:model-value="(d) => (form._release_at_obj = d)"
              />
            </div>

            <div class="flex items-center gap-2">
              <Switch id="allotment-active" v-model="form.is_active" />
              <Label for="allotment-active" class="cursor-pointer">Active</Label>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <Button variant="outline" type="button" @click="dialogOpen = false">Cancel</Button>
              <Button type="submit" :disabled="saving">
                <Spinner v-if="saving" />
                {{ editing ? "Save Changes" : "Create" }}
              </Button>
            </div>
          </form>
        </div>
      </template>
    </DialogResponsive>

    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Delete allotment?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            This allotment block will be moved to trash.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <Button variant="outline" type="button" @click="deleteDialogOpen = false">
              Cancel
            </Button>
            <Button variant="destructive" :disabled="deleting" @click="handleDelete">
              <Spinner v-if="deleting" />
              {{ deleting ? "Deleting..." : "Delete" }}
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { RangeCalendarPicker } from "@/components/ui/range-calendar-picker";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { Switch } from "@/components/ui/switch";
import { TableData } from "@/components/ui/table-data";
import { parseLocalDateString, toLocalDateString, toLocalDateTimeString } from "@/lib/utils";
import { PopoverClose } from "reka-ui";
import { computed, defineComponent, h, reactive, ref, resolveComponent } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({
  eventId: { type: [Number, String], required: true },
  hotelSlug: { type: String, required: true },
});

const baseUrl = computed(() => `/api/events/${props.eventId}/hotels/${props.hotelSlug}/allotments`);
const roomsUrl = computed(
  () => `/api/events/${props.eventId}/hotels/${props.hotelSlug}/room-types`
);

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("allotments.create"));
const canDelete = computed(() => hasPermission("allotments.delete"));

const { data, pending, refresh } = await useLazySanctumFetch(
  () => `${baseUrl.value}?per_page=100`,
  {
    key: () => `hotel-${props.eventId}-${props.hotelSlug}-allotments`,
  }
);

const allotments = computed(() => data.value?.data ?? []);
const meta = computed(
  () =>
    data.value?.meta ?? {
      current_page: 1,
      last_page: 1,
      per_page: 50,
      total: allotments.value.length,
    }
);

const { data: roomsData } = await useLazySanctumFetch(() => `${roomsUrl.value}?per_page=100`, {
  key: () => `hotel-${props.eventId}-${props.hotelSlug}-rooms-for-allotments`,
});
const roomTypes = computed(() => roomsData.value?.data ?? []);

const dialogOpen = ref(false);
const editing = ref(null);
const saving = ref(false);

const form = reactive({
  room_type_id: null,
  start_date: "",
  end_date: "",
  quantity: 1,
  base_rate_override: null,
  surcharge_type: null,
  surcharge_amount: null,
  release_at: "",
  is_active: true,
  // Underscored mirrors are Date objects bound to DatePicker. Serialised to
  // strings in handleSubmit so the backend keeps receiving its expected
  // ISO-flavoured payload.
  _start_date_obj: null,
  _end_date_obj: null,
  _release_at_obj: null,
});

const resetForm = () => {
  Object.assign(form, {
    room_type_id: null,
    start_date: "",
    end_date: "",
    quantity: 1,
    base_rate_override: null,
    surcharge_type: null,
    surcharge_amount: null,
    release_at: "",
    is_active: true,
    _start_date_obj: null,
    _end_date_obj: null,
    _release_at_obj: null,
  });
};

const openCreateDialog = () => {
  editing.value = null;
  resetForm();
  dialogOpen.value = true;
};

const openEditDialog = (a) => {
  editing.value = a;
  Object.assign(form, {
    room_type_id: a.room_type_id,
    start_date: a.start_date,
    end_date: a.end_date,
    quantity: a.quantity,
    base_rate_override: a.base_rate_override ?? null,
    surcharge_type: a.surcharge_type,
    surcharge_amount: a.surcharge_amount,
    release_at: a.release_at ? a.release_at.slice(0, 16) : "",
    is_active: a.is_active,
    _start_date_obj: parseLocalDateString(a.start_date),
    _end_date_obj: parseLocalDateString(a.end_date),
    _release_at_obj: a.release_at ? new Date(a.release_at) : null,
  });
  dialogOpen.value = true;
};

const onDateRangeUpdate = (range) => {
  form._start_date_obj = range?.start ?? null;
  form._end_date_obj = range?.end ?? null;
};

const handleSubmit = async () => {
  saving.value = true;
  try {
    const payload = {
      ...form,
      start_date: form._start_date_obj ? toLocalDateString(form._start_date_obj) : "",
      end_date: form._end_date_obj ? toLocalDateString(form._end_date_obj) : "",
      release_at: form._release_at_obj ? toLocalDateTimeString(form._release_at_obj) : null,
    };
    delete payload._start_date_obj;
    delete payload._end_date_obj;
    delete payload._release_at_obj;
    if (!payload.surcharge_type) {
      payload.surcharge_amount = null;
    }
    if (!payload.release_at) {
      payload.release_at = null;
    }
    if (payload.base_rate_override === "" || payload.base_rate_override === undefined) {
      payload.base_rate_override = null;
    }

    if (editing.value) {
      await client(`${baseUrl.value}/${editing.value.id}`, { method: "PUT", body: payload });
      toast.success("Allotment updated");
    } else {
      await client(baseUrl.value, { method: "POST", body: payload });
      toast.success("Allotment created");
    }
    dialogOpen.value = false;
    await refresh();
  } catch (err) {
    toast.error("Save failed", { description: err?.data?.message || err?.message });
  } finally {
    saving.value = false;
  }
};

const deleteDialogOpen = ref(false);
const deletingItem = ref(null);
const deleting = ref(false);

const confirmDelete = (a) => {
  deletingItem.value = a;
  deleteDialogOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingItem.value) return;
  deleting.value = true;
  try {
    await client(`${baseUrl.value}/${deletingItem.value.id}`, { method: "DELETE" });
    toast.success("Allotment deleted");
    deleteDialogOpen.value = false;
    await refresh();
  } catch (err) {
    toast.error("Delete failed", { description: err?.data?.message || err?.message });
  } finally {
    deleting.value = false;
  }
};

const formatDate = (iso) =>
  iso
    ? new Date(iso).toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
      })
    : "-";

const RowActions = defineComponent({
  props: { item: { type: Object, required: true } },
  setup(p) {
    return () =>
      h("div", { class: "flex justify-end" }, [
        h(
          Popover,
          {},
          {
            default: () => [
              h(
                PopoverTrigger,
                { asChild: true },
                {
                  default: () =>
                    h(
                      "button",
                      {
                        class:
                          "hover:bg-muted data-[state=open]:bg-muted inline-flex size-8 items-center justify-center rounded-md",
                      },
                      [h(resolveComponent("Icon"), { name: "lucide:ellipsis", class: "size-4" })]
                    ),
                }
              ),
              h(
                PopoverContent,
                { align: "end", class: "w-40 p-1" },
                {
                  default: () =>
                    h("div", { class: "flex flex-col" }, [
                      h(
                        PopoverClose,
                        { asChild: true },
                        {
                          default: () =>
                            h(
                              "button",
                              {
                                class:
                                  "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                                onClick: () => openEditDialog(p.item),
                              },
                              [
                                h(resolveComponent("Icon"), {
                                  name: "lucide:pencil-line",
                                  class: "size-4 shrink-0",
                                }),
                                h("span", {}, "Edit"),
                              ]
                            ),
                        }
                      ),
                      canDelete.value
                        ? h(
                            PopoverClose,
                            { asChild: true },
                            {
                              default: () =>
                                h(
                                  "button",
                                  {
                                    class:
                                      "hover:bg-destructive/10 text-destructive rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                                    onClick: () => confirmDelete(p.item),
                                  },
                                  [
                                    h(resolveComponent("Icon"), {
                                      name: "lucide:trash",
                                      class: "size-4 shrink-0",
                                    }),
                                    h("span", {}, "Delete"),
                                  ]
                                ),
                            }
                          )
                        : null,
                    ]),
                }
              ),
            ],
          }
        ),
      ]);
  },
});

const columns = [
  {
    header: "Room Type",
    accessorKey: "room_type",
    cell: ({ row }) =>
      h("span", { class: "font-medium tracking-tight" }, row.original.room_type?.name || "-"),
    size: 200,
    enableHiding: false,
  },
  {
    header: "Date Range",
    accessorKey: "start_date",
    cell: ({ row }) =>
      h(
        "span",
        { class: "text-sm tracking-tight whitespace-nowrap" },
        `${formatDate(row.original.start_date)} → ${formatDate(row.original.end_date)}`
      ),
    size: 220,
  },
  {
    header: "Quantity",
    accessorKey: "quantity",
    cell: ({ row }) =>
      h("span", { class: "tabular-nums text-sm tracking-tight" }, row.original.quantity),
    size: 90,
  },
  {
    header: "Surcharge",
    accessorKey: "surcharge_type",
    cell: ({ row }) => {
      const a = row.original;
      const text = a.surcharge_type
        ? `${new Intl.NumberFormat("id-ID").format(Number(a.surcharge_amount) || 0)}${
            a.surcharge_type === "percentage" ? "%" : ""
          } ${a.surcharge_type}`
        : "-";
      return h("span", { class: "text-sm tracking-tight text-muted-foreground" }, text);
    },
    size: 160,
  },
  {
    header: "Status",
    accessorKey: "is_active",
    cell: ({ row }) =>
      h(
        "span",
        {
          class: [
            "inline-flex items-center rounded-full px-2 py-0.5 text-xs tracking-tight",
            row.original.is_active
              ? "bg-success/15 text-success-foreground"
              : "bg-muted text-muted-foreground",
          ],
        },
        row.original.is_active ? "Active" : "Inactive"
      ),
    size: 90,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => h(RowActions, { item: row.original }),
    size: 60,
    enableHiding: false,
  },
];
</script>
