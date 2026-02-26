<template>
  <div class="flex flex-col gap-y-6">
    <div class="space-y-1">
      <h4 class="font-semibold tracking-tight">Booth Information</h4>
      <p class="text-muted-foreground text-sm tracking-tight">
        Booth assignment and details (staff only).
      </p>
    </div>

    <form @submit.prevent="saveBooth" class="max-w-lg space-y-4">
      <div class="space-y-2">
        <Label for="booth_number">Booth Number</Label>
        <Input id="booth_number" v-model="form.booth_number" placeholder="e.g. B-101" />
      </div>

      <div class="grid grid-cols-2 gap-x-2 gap-y-4">
        <div class="space-y-2">
          <Label for="booth_size">Booth Size (sqm)</Label>
          <Input id="booth_size" v-model="form.booth_size" type="number" step="0.01" placeholder="e.g. 9.00" />
        </div>
        <div class="space-y-2">
          <Label for="booth_price">Booth Price (Rp)</Label>
          <Input id="booth_price" v-model="form.booth_price" type="number" min="0" placeholder="e.g. 50000000" />
        </div>
      </div>

      <div class="space-y-2">
        <Label>Booth Type</Label>
        <Select v-model="form.booth_type">
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select type" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="raw_space">Raw Space</SelectItem>
            <SelectItem value="standard_shell_scheme">Standard Shell Scheme</SelectItem>
            <SelectItem value="enhanced_shell_scheme">Enhanced Shell Scheme</SelectItem>
          </SelectContent>
        </Select>
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
        <Label>Status</Label>
        <Select v-model="form.status">
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

      <div class="space-y-2">
        <Label for="notes">Notes</Label>
        <Textarea id="notes" v-model="form.notes" rows="3" placeholder="Internal notes..." />
      </div>

      <div class="space-y-2">
        <Label for="promotion_post_limit">Promotion Post Limit</Label>
        <Input id="promotion_post_limit" v-model.number="form.promotion_post_limit" type="number" min="1" max="100" placeholder="1" />
        <p class="text-muted-foreground text-xs">Maximum number of promotion posts this exhibitor can create.</p>
      </div>

      <div class="flex gap-2">
        <Button type="submit" :disabled="saving" size="sm">
          <Icon v-if="saving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
          Save Booth Info
        </Button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

const props = defineProps({ brandEvent: Object });
const emit = defineEmits(["refresh"]);
const route = useRoute();
const client = useSanctumClient();

const project = inject("project");

const saving = ref(false);
const form = reactive({
  booth_number: props.brandEvent?.booth_number || "",
  booth_size: props.brandEvent?.booth_size || "",
  booth_price: props.brandEvent?.booth_price || "",
  booth_type: props.brandEvent?.booth_type || "",
  sales_id: props.brandEvent?.sales?.id || null,
  status: props.brandEvent?.status || "draft",
  notes: props.brandEvent?.notes || "",
  promotion_post_limit: props.brandEvent?.promotion_post_limit || 1,
});

const members = computed(() => project.value?.members || []);
const selectedSales = computed(() =>
  members.value.find((u) => u.id === form.sales_id) || null
);

async function saveBooth() {
  saving.value = true;
  try {
    await client(`/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${route.params.brandSlug}`, {
      method: "PUT",
      body: form,
    });
    toast.success("Booth info updated");
    emit("refresh");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to update");
  } finally {
    saving.value = false;
  }
}
</script>
