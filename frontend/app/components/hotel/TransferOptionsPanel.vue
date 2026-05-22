<template>
  <div class="space-y-4">
    <div class="flex justify-end">
      <Button v-if="canEdit" size="sm" @click="openCreateDialog">
        <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
        Add Transfer Option
      </Button>
    </div>

    <TableData
      :data="options"
      :columns="columns"
      :meta="meta"
      model="transfers"
      label="Transfer Option"
      :pending="pending"
      :client-only="true"
      :show-add-button="false"
      :column-toggle="false"
      :searchable="false"
      :initial-pagination="{ pageIndex: 0, pageSize: 50 }"
      :initial-sorting="[]"
      @refresh="refresh"
    />

    <DialogResponsive
      v-model:open="dialogOpen"
      dialog-max-width="28rem"
      :overflow-content="true"
    >
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">
            {{ editing ? "Edit Transfer Option" : "Add Transfer Option" }}
          </h3>

          <form @submit.prevent="handleSubmit" class="mt-4 space-y-3">
            <div class="space-y-2">
              <Label>Label</Label>
              <Input v-model="form.label" required placeholder="Airport Sedan (CGK)" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label for="transfer_direction">Direction</Label>
                <Select v-model="form.direction">
                  <SelectTrigger id="transfer_direction" class="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="in">Arrival (In)</SelectItem>
                    <SelectItem value="out">Departure (Out)</SelectItem>
                    <SelectItem value="both">Both Ways</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>Vehicle Type</Label>
                <Input v-model="form.vehicle_type" placeholder="Sedan / MPV / Van" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label>Max Pax</Label>
                <Input v-model.number="form.max_pax" type="number" min="1" required />
              </div>
              <div class="space-y-2">
                <Label>Price (IDR)</Label>
                <Input v-model.number="form.price" type="number" min="0" required />
              </div>
            </div>

            <div class="flex items-center gap-2">
              <Switch id="transfer-active" v-model="form.is_active" />
              <Label for="transfer-active" class="cursor-pointer">Active</Label>
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
          <div class="text-primary text-lg font-semibold tracking-tight">
            Delete transfer option?
          </div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            "{{ deletingItem?.label }}" will be removed.
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
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { TableData } from "@/components/ui/table-data";
import { PopoverClose } from "reka-ui";
import { computed, defineComponent, h, reactive, ref, resolveComponent } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({
  eventId: { type: [Number, String], required: true },
  hotelSlug: { type: String, required: true },
});

const baseUrl = computed(
  () => `/api/events/${props.eventId}/hotels/${props.hotelSlug}/transfer-options`
);

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canEdit = computed(() => hasPermission("hotels.update"));

const { data, pending, refresh } = await useLazySanctumFetch(
  () => `${baseUrl.value}?per_page=100`,
  {
    key: () => `hotel-${props.eventId}-${props.hotelSlug}-transfers`,
  }
);

const options = computed(() => data.value?.data ?? []);
const meta = computed(
  () => data.value?.meta ?? { current_page: 1, last_page: 1, per_page: 50, total: options.value.length }
);

const dialogOpen = ref(false);
const editing = ref(null);
const saving = ref(false);

const form = reactive({
  label: "",
  direction: "both",
  vehicle_type: "",
  max_pax: 2,
  price: 0,
  is_active: true,
});

const resetForm = () => {
  Object.assign(form, {
    label: "",
    direction: "both",
    vehicle_type: "",
    max_pax: 2,
    price: 0,
    is_active: true,
  });
};

const openCreateDialog = () => {
  editing.value = null;
  resetForm();
  dialogOpen.value = true;
};

const openEditDialog = (opt) => {
  editing.value = opt;
  Object.assign(form, {
    label: opt.label,
    direction: opt.direction,
    vehicle_type: opt.vehicle_type || "",
    max_pax: opt.max_pax,
    price: opt.price,
    is_active: opt.is_active,
  });
  dialogOpen.value = true;
};

const handleSubmit = async () => {
  saving.value = true;
  try {
    const payload = { ...form };
    if (editing.value) {
      await client(`${baseUrl.value}/${editing.value.id}`, { method: "PUT", body: payload });
      toast.success("Transfer option updated");
    } else {
      await client(baseUrl.value, { method: "POST", body: payload });
      toast.success("Transfer option created");
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

const confirmDelete = (opt) => {
  deletingItem.value = opt;
  deleteDialogOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingItem.value) return;
  deleting.value = true;
  try {
    await client(`${baseUrl.value}/${deletingItem.value.id}`, { method: "DELETE" });
    toast.success("Transfer option deleted");
    deleteDialogOpen.value = false;
    await refresh();
  } catch (err) {
    toast.error("Delete failed", { description: err?.data?.message || err?.message });
  } finally {
    deleting.value = false;
  }
};

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);

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
                      canEdit.value
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
    header: "Label",
    accessorKey: "label",
    cell: ({ row }) =>
      h("span", { class: "font-medium tracking-tight" }, row.original.label),
    size: 240,
    enableHiding: false,
  },
  {
    header: "Direction",
    accessorKey: "direction",
    cell: ({ row }) =>
      h(
        "span",
        { class: "text-sm tracking-tight text-muted-foreground" },
        row.original.direction_label || row.original.direction || "-"
      ),
    size: 140,
  },
  {
    header: "Vehicle",
    accessorKey: "vehicle_type",
    cell: ({ row }) =>
      h(
        "span",
        { class: "text-sm tracking-tight text-muted-foreground" },
        row.original.vehicle_type || "-"
      ),
    size: 140,
  },
  {
    header: "Max Pax",
    accessorKey: "max_pax",
    cell: ({ row }) =>
      h("span", { class: "tabular-nums text-sm tracking-tight" }, row.original.max_pax),
    size: 80,
  },
  {
    header: "Price",
    accessorKey: "price",
    cell: ({ row }) =>
      h(
        "span",
        { class: "tabular-nums text-sm tracking-tight" },
        `Rp${formatRupiah(row.original.price)}`
      ),
    size: 140,
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
