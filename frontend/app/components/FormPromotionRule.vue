<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-8">
    <!-- Basic info -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Basic info</div>
        <div class="frame-description">Name, slug, and description for this rule.</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label for="name">Name</Label>
            <Input id="name" v-model="form.name" type="text" required maxlength="255" />
            <FieldError :errors="errors.name" />
          </div>

          <div class="space-y-2">
            <Label for="slug">Slug</Label>
            <Input
              id="slug"
              v-model="form.slug"
              type="text"
              maxlength="120"
              placeholder="auto-generated from name"
              pattern="[a-z0-9\-]+"
            />
            <p class="text-muted-foreground text-xs tracking-tight">Lowercase letters, numbers, and dashes only.</p>
            <FieldError :errors="errors.slug" />
          </div>

          <div class="space-y-2">
            <Label for="description">Description</Label>
            <Textarea id="description" v-model="form.description" rows="3" maxlength="2000" />
            <FieldError :errors="errors.description" />
          </div>
        </div>
      </div>
    </div>

    <!-- Kind & Value -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Value</div>
        <div class="frame-description">Discount or penalty, percentage or fixed amount.</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="grid grid-cols-2 gap-x-2 gap-y-6">
            <div class="space-y-2">
              <Label for="kind">Kind</Label>
              <Select v-model="form.kind">
                <SelectTrigger class="w-full"><SelectValue placeholder="Select kind" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="discount">Discount (reduces price)</SelectItem>
                  <SelectItem value="penalty">Penalty (adds to price)</SelectItem>
                </SelectContent>
              </Select>
              <FieldError :errors="errors.kind" />
            </div>

            <div class="space-y-2">
              <Label for="value_type">Value Type</Label>
              <Select v-model="form.value_type">
                <SelectTrigger class="w-full"><SelectValue placeholder="Select type" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="percentage">Percentage (%)</SelectItem>
                  <SelectItem value="fixed_amount">Fixed Amount (Rp)</SelectItem>
                  <SelectItem v-if="form.kind === 'discount'" value="buy_x_get_y">Buy X Get Y Free</SelectItem>
                  <SelectItem v-if="form.kind === 'discount'" value="tiered_percentage">Tiered Percentage</SelectItem>
                  <SelectItem v-if="form.kind === 'discount'" value="tiered_fixed_amount">Tiered Fixed Amount</SelectItem>
                  <SelectItem v-if="form.kind === 'discount'" value="bundle_price">Bundle Price</SelectItem>
                  <SelectItem v-if="form.kind === 'discount'" value="free_addon">Free Add-on</SelectItem>
                </SelectContent>
              </Select>
              <FieldError :errors="errors.value_type" />
            </div>
          </div>

          <div v-if="!usesValueConfig" class="grid grid-cols-2 gap-x-2 gap-y-6">
            <div class="space-y-2">
              <Label for="value">
                Value                <span class="text-muted-foreground text-xs ml-1">
                  ({{ form.value_type === "percentage" ? "%" : "Rp" }})
                </span>
              </Label>
              <InputGroup>
                <InputNumber
                  id="value"
                  v-model="form.value"
                  :min="0"
                  decimal
                  required
                  data-slot="input-group-control"
                  class="flex-1 rounded-none border-0 shadow-none focus-visible:ring-0 focus-visible:ring-transparent dark:bg-transparent"
                />
                <InputGroupAddon :align="form.value_type === 'percentage' ? 'inline-end' : 'inline-start'">
                  <InputGroupText>{{ form.value_type === "percentage" ? "%" : "Rp" }}</InputGroupText>
                </InputGroupAddon>
              </InputGroup>
              <FieldError :errors="errors.value" />
            </div>

            <div class="space-y-2">
              <Label for="max_discount_amount">Max Discount Cap (Rp)</Label>
              <InputGroup>
                <InputNumber
                  id="max_discount_amount"
                  v-model="form.max_discount_amount"
                  :min="0"
                  placeholder="Unlimited"
                  :disabled="form.value_type !== 'percentage' || form.kind !== 'discount'"
                  data-slot="input-group-control"
                  class="flex-1 rounded-none border-0 shadow-none focus-visible:ring-0 focus-visible:ring-transparent dark:bg-transparent"
                />
                <InputGroupAddon>
                  <InputGroupText>Rp</InputGroupText>
                </InputGroupAddon>
              </InputGroup>
              <p class="text-muted-foreground text-xs tracking-tight">Only used for percentage discounts.</p>
              <FieldError :errors="errors.max_discount_amount" />
            </div>
          </div>

          <!-- Buy X Get Y config -->
          <div v-if="form.value_type === 'buy_x_get_y'" class="grid grid-cols-2 gap-x-2 gap-y-6">
            <div class="space-y-2">
              <Label for="buy_qty">Buy quantity (X)</Label>
              <InputNumber id="buy_qty" v-model="form.value_config.buy_qty" :min="1" />
              <p class="text-muted-foreground text-xs tracking-tight">Customer must purchase this many units.</p>
              <FieldError :errors="errors['value_config.buy_qty']" />
            </div>
            <div class="space-y-2">
              <Label for="get_free_qty">Get free (Y)</Label>
              <InputNumber id="get_free_qty" v-model="form.value_config.get_free_qty" :min="1" />
              <p class="text-muted-foreground text-xs tracking-tight">Cheapest units are picked for the free slots.</p>
              <FieldError :errors="errors['value_config.get_free_qty']" />
            </div>
          </div>

          <!-- Tiered config -->
          <div v-if="form.value_type === 'tiered_percentage' || form.value_type === 'tiered_fixed_amount'" class="space-y-3">
            <div class="grid grid-cols-2 gap-x-2 gap-y-6">
              <div class="space-y-2">
                <Label for="metric">Tier Metric</Label>
                <Select v-model="form.value_config.metric">
                  <SelectTrigger class="w-full"><SelectValue placeholder="Select metric" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="qty">By quantity</SelectItem>
                    <SelectItem value="amount">By total amount</SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-muted-foreground text-xs tracking-tight">Which value selects the tier.</p>
              </div>
            </div>

            <div class="space-y-2">
              <Label>Tiers</Label>
              <div v-for="(tier, idx) in form.value_config.tiers ?? []" :key="idx" class="grid grid-cols-[1fr_1fr_auto] gap-2 items-center">
                <InputNumber v-model="tier.min" :min="0" placeholder="Min (qty/amount)" />
                <InputGroup>
                  <InputNumber
                    v-model="tier.value"
                    :min="0"
                    decimal
                    :placeholder="form.value_type === 'tiered_percentage' ? 'Percent' : 'Amount'"
                    data-slot="input-group-control"
                    class="flex-1 rounded-none border-0 shadow-none focus-visible:ring-0 focus-visible:ring-transparent dark:bg-transparent"
                  />
                  <InputGroupAddon :align="form.value_type === 'tiered_percentage' ? 'inline-end' : 'inline-start'">
                    <InputGroupText>{{ form.value_type === "tiered_percentage" ? "%" : "Rp" }}</InputGroupText>
                  </InputGroupAddon>
                </InputGroup>
                <Button type="button" variant="outline" size="sm" @click="removeTier(idx)">
                  <Icon name="lucide:x" class="size-4" />
                </Button>
              </div>
              <Button type="button" variant="outline" size="sm" @click="addTier">
                <Icon name="lucide:plus" class="size-4" />
                Add Tier
              </Button>
              <p class="text-muted-foreground text-xs tracking-tight">
                Highest matching tier wins. Example: min=3 → 5%, min=5 → 10%.
              </p>
              <FieldError :errors="errors['value_config.tiers']" />
            </div>
          </div>

          <!-- Bundle config -->
          <div v-if="form.value_type === 'bundle_price'" class="grid grid-cols-2 gap-x-2 gap-y-6">
            <div class="space-y-2">
              <Label for="bundle_qty">Bundle quantity</Label>
              <InputNumber id="bundle_qty" v-model="form.value_config.bundle_qty" :min="1" />
              <p class="text-muted-foreground text-xs tracking-tight">Every N units priced as a bundle.</p>
              <FieldError :errors="errors['value_config.bundle_qty']" />
            </div>
            <div class="space-y-2">
              <Label for="bundle_price">Bundle price (Rp)</Label>
              <InputGroup>
                <InputNumber
                  id="bundle_price"
                  v-model="form.value_config.bundle_price"
                  :min="0"
                  decimal
                  data-slot="input-group-control"
                  class="flex-1 rounded-none border-0 shadow-none focus-visible:ring-0 focus-visible:ring-transparent dark:bg-transparent"
                />
                <InputGroupAddon>
                  <InputGroupText>Rp</InputGroupText>
                </InputGroupAddon>
              </InputGroup>
              <p class="text-muted-foreground text-xs tracking-tight">Fixed total for each bundle.</p>
              <FieldError :errors="errors['value_config.bundle_price']" />
            </div>
          </div>

          <!-- Free Add-on config -->
          <div v-if="form.value_type === 'free_addon'" class="grid grid-cols-2 gap-x-2 gap-y-6">
            <div class="space-y-2">
              <Label for="max_free_qty">Max free units</Label>
              <InputNumber id="max_free_qty" v-model="form.value_config.max_free_qty" :min="1" placeholder="Unlimited" />
              <p class="text-muted-foreground text-xs tracking-tight">Limit how many add-ons can be free.</p>
              <FieldError :errors="errors['value_config.max_free_qty']" />
            </div>
            <div class="space-y-2">
              <Label>Target Line Categories</Label>
              <div class="flex flex-wrap gap-2 pt-2">
                <label v-for="key in availableLineKeys" :key="key" class="flex items-center gap-1.5 text-sm tracking-tight">
                  <Checkbox
                    :model-value="(form.value_config.target_line_keys ?? []).includes(key)"
                    @update:model-value="(v) => toggleLineKey(key, v)"
                  />
                  {{ key }}
                </label>
              </div>
              <p class="text-muted-foreground text-xs tracking-tight">Discount only applies to selected line types.</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-x-2 gap-y-6">
            <div class="space-y-2">
              <Label for="currency">Currency</Label>
              <Select v-model="form.currency">
                <SelectTrigger id="currency" class="w-full"><SelectValue placeholder="Select currency" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="IDR">IDR (Rupiah)</SelectItem>
                  <SelectItem value="USD">USD (Dollar)</SelectItem>
                </SelectContent>
              </Select>
              <p class="text-muted-foreground text-xs tracking-tight">
                Order currency this rule applies to. A rule only applies to orders of the same currency.
              </p>
              <FieldError :errors="errors.currency" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-x-2 gap-y-6">
            <div class="space-y-2">
              <Label for="min_purchase_amount">Minimum Purchase (Rp)</Label>
              <InputGroup>
                <InputNumber
                  id="min_purchase_amount"
                  v-model="form.min_purchase_amount"
                  :min="0"
                  placeholder="No minimum"
                  data-slot="input-group-control"
                  class="flex-1 rounded-none border-0 shadow-none focus-visible:ring-0 focus-visible:ring-transparent dark:bg-transparent"
                />
                <InputGroupAddon>
                  <InputGroupText>Rp</InputGroupText>
                </InputGroupAddon>
              </InputGroup>
              <FieldError :errors="errors.min_purchase_amount" />
            </div>

            <div class="space-y-2">
              <Label for="priority">Priority</Label>
              <InputNumber id="priority" v-model="form.priority" :min="0" :max="32000" />
              <p class="text-muted-foreground text-xs tracking-tight">Lower number applies first. Default 100.</p>
              <FieldError :errors="errors.priority" />
            </div>
          </div>

          <div class="flex items-center gap-2">
            <Switch id="applies_before_tax" v-model="form.applies_before_tax" />
            <Label for="applies_before_tax" class="cursor-pointer">
              Apply before tax
              <span class="text-muted-foreground text-xs ml-1">(default behavior, matches PPN convention)</span>
            </Label>
          </div>
        </div>
      </div>
    </div>

    <!-- Stacking -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Stacking</div>
        <div class="frame-description">Whether this rule can combine with other adjustments.</div>
      </div>
      <div class="frame-panel">
        <div class="space-y-2">
          <Label for="stacking_mode">Stacking Mode</Label>
          <Select v-model="form.stacking_mode">
            <SelectTrigger class="w-full"><SelectValue placeholder="Select stacking mode" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="exclusive">Exclusive (cannot combine with any other)</SelectItem>
              <SelectItem value="combinable_with_promo">Combinable with Promo</SelectItem>
              <SelectItem value="combinable_with_manual">Combinable with Manual</SelectItem>
              <SelectItem value="combinable_with_all">Combinable with All</SelectItem>
            </SelectContent>
          </Select>
          <FieldError :errors="errors.stacking_mode" />
        </div>
      </div>
    </div>

    <!-- Validity & Active -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Validity Window</div>
        <div class="frame-description">Optional rule-level start/end dates. Codes can override.</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-2 gap-x-2 gap-y-6">
          <div class="space-y-2">
            <Label for="starts_at">Starts At</Label>
            <DatePicker
              v-model="form.starts_at"
              with-time
              placeholder="Select start date & time"
              :default-hour="0"
              :default-minute="0"
            />
            <FieldError :errors="errors.starts_at" />
          </div>

          <div class="space-y-2">
            <Label for="ends_at">Ends At</Label>
            <DatePicker
              v-model="form.ends_at"
              with-time
              placeholder="Select end date & time"
              :default-hour="23"
              :default-minute="59"
            />
            <FieldError :errors="errors.ends_at" />
          </div>
        </div>

        <div class="flex items-center gap-3 mt-6">
          <Switch id="is_active" v-model="form.is_active" />
          <Label for="is_active" class="cursor-pointer">Active</Label>
        </div>

        <div class="flex items-center gap-3 mt-3">
          <Switch id="revert_usage_on_cancel" v-model="form.revert_usage_on_cancel" />
          <Label for="revert_usage_on_cancel" class="cursor-pointer">
            Revert usage when reservation cancelled
            <span class="text-muted-foreground text-xs ml-1">(code becomes reusable on cancel)</span>
          </Label>
        </div>
      </div>
    </div>

    <!-- Penalty Trigger (conditional) -->
    <div v-if="form.kind === 'penalty'" class="frame">
      <div class="frame-header">
        <div class="frame-title">Penalty Trigger</div>
        <div class="frame-description">When this penalty should automatically apply.</div>
      </div>
      <div class="frame-panel">
        <div class="space-y-6">
          <div class="space-y-2">
            <Label for="trigger_type">Trigger Type</Label>
            <Select v-model="form.trigger_type">
              <SelectTrigger class="w-full"><SelectValue placeholder="Select trigger" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="manual">Manual (admin trigger only)</SelectItem>
                <SelectItem value="booking_window">Booking Window (current time inside event period)</SelectItem>
                <SelectItem value="event_period">Event Period (uses Event.normal/onsite_order_* columns)</SelectItem>
                <SelectItem value="date_range">Date Range (fixed start/end dates)</SelectItem>
                <SelectItem value="lead_time">Lead Time (booked N days before check-in)</SelectItem>
                <SelectItem value="cancellation_window">Cancellation Window (cancel near check-in)</SelectItem>
              </SelectContent>
            </Select>
            <FieldError :errors="errors.trigger_type" />
          </div>

          <!-- Trigger config inputs based on type -->
          <div v-if="form.trigger_type === 'booking_window' || form.trigger_type === 'event_period'" class="space-y-2">
            <Label for="trigger_window">Window</Label>
            <Select v-model="triggerWindow">
              <SelectTrigger class="w-full"><SelectValue placeholder="Select window" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="normal">Normal Period</SelectItem>
                <SelectItem value="onsite">Onsite Period</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div v-if="form.trigger_type === 'date_range'" class="grid grid-cols-2 gap-x-2 gap-y-6">
            <div class="space-y-2">
              <Label for="trigger_start">Start Date</Label>
              <DatePicker
                v-model="triggerStart"
                with-time
                placeholder="Select start date & time"
                :default-hour="0"
                :default-minute="0"
              />
            </div>
            <div class="space-y-2">
              <Label for="trigger_end">End Date</Label>
              <DatePicker
                v-model="triggerEnd"
                with-time
                placeholder="Select end date & time"
                :default-hour="23"
                :default-minute="59"
              />
            </div>
          </div>

          <div v-if="form.trigger_type === 'lead_time' || form.trigger_type === 'cancellation_window'" class="grid grid-cols-2 gap-x-2 gap-y-6">
            <div class="space-y-2">
              <Label for="trigger_days">
                {{ form.trigger_type === "lead_time" ? "Max Days Before Check-In" : "Min Days Before Check-In" }}
              </Label>
              <InputNumber id="trigger_days" v-model="triggerDays" :min="0" />
            </div>
            <div class="space-y-2">
              <Label for="trigger_operator">Operator</Label>
              <Select v-model="triggerOperator">
                <SelectTrigger class="w-full"><SelectValue placeholder="Operator" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="lt">Less Than (&lt;)</SelectItem>
                  <SelectItem value="lte">Less Than or Equal (≤)</SelectItem>
                  <SelectItem value="gt">Greater Than (&gt;)</SelectItem>
                  <SelectItem value="gte">Greater Than or Equal (≥)</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Targeting -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Targeting</div>
        <div class="frame-description">Optional scope to specific entities. Leave empty for all.</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label>Purchase Types</Label>
            <div class="flex flex-wrap gap-3">
              <label v-for="type in availableTargetTypes" :key="type" class="flex items-center gap-2 cursor-pointer">
                <Checkbox
                  :model-value="form.target_types?.includes(type)"
                  @update:model-value="toggleTargetType(type, $event)"
                />
                <span class="text-sm tracking-tight">{{ type }}</span>
              </label>
            </div>
            <p class="text-muted-foreground text-xs tracking-tight">Empty = applies to all purchase types.</p>
            <FieldError :errors="errors.target_types" />
          </div>

          <div class="grid grid-cols-2 gap-x-2 gap-y-6">
            <div class="space-y-2">
              <Label for="event_id">Event Scope (optional)</Label>
              <Input id="event_id" v-model.number="form.event_id" type="number" min="0" placeholder="Event ID" />
              <p class="text-muted-foreground text-xs tracking-tight">Restrict to specific event.</p>
              <FieldError :errors="errors.event_id" />
            </div>

            <div class="space-y-2">
              <Label for="project_id">Project Scope (optional)</Label>
              <Input id="project_id" v-model.number="form.project_id" type="number" min="0" placeholder="Project ID" />
              <FieldError :errors="errors.project_id" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Applicability -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Applicability</div>
        <div class="frame-description">Conditions the cart must satisfy. Leave fields empty for no restriction.</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="grid grid-cols-2 gap-x-2 gap-y-6">
            <div class="space-y-2">
              <Label for="appl_min_nights">Minimum Nights</Label>
              <InputNumber id="appl_min_nights" v-model="applicabilityForm.min_nights" :min="0" placeholder="No minimum" />
              <p class="text-muted-foreground text-xs tracking-tight">Sum of nights across all items.</p>
            </div>
            <div class="space-y-2">
              <Label for="appl_min_qty">Minimum Quantity</Label>
              <InputNumber id="appl_min_qty" v-model="applicabilityForm.min_qty" :min="0" placeholder="No minimum" />
              <p class="text-muted-foreground text-xs tracking-tight">Sum of qty across all items.</p>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="appl_events">Event IDs (CSV)</Label>
            <Input id="appl_events" v-model="applicabilityForm.events" type="text" placeholder="e.g. 1, 5, 12" />
            <p class="text-muted-foreground text-xs tracking-tight">Comma-separated event IDs. Cart must belong to one of these.</p>
          </div>

          <div class="space-y-2">
            <Label for="appl_hotels">Hotel IDs (CSV)</Label>
            <Input id="appl_hotels" v-model="applicabilityForm.hotels" type="text" placeholder="e.g. 3, 7" />
          </div>

          <div class="space-y-2">
            <Label for="appl_room_types">Room Type IDs (CSV)</Label>
            <Input id="appl_room_types" v-model="applicabilityForm.room_types" type="text" placeholder="e.g. 10, 11" />
            <p class="text-muted-foreground text-xs tracking-tight">Restrict bonus + discount to these room types (BOGO/free addon).</p>
          </div>

          <div class="space-y-2">
            <Label for="appl_domains">Guest Email Domains (CSV)</Label>
            <Input id="appl_domains" v-model="applicabilityForm.guest_email_domains" type="text" placeholder="e.g. @company.com, @askindo.com" />
            <p class="text-muted-foreground text-xs tracking-tight">Customer email must end with one of these.</p>
          </div>

          <div class="space-y-2">
            <Label>Weekdays (when promo is valid)</Label>
            <div class="flex flex-wrap gap-3">
              <label v-for="day in weekdays" :key="day.value" class="flex items-center gap-2 cursor-pointer">
                <Checkbox
                  :model-value="applicabilityForm.weekdays.includes(day.value)"
                  @update:model-value="toggleWeekday(day.value, $event)"
                />
                <span class="text-sm tracking-tight">{{ day.label }}</span>
              </label>
            </div>
            <p class="text-muted-foreground text-xs tracking-tight">Empty = valid all days.</p>
          </div>

          <div class="flex items-center gap-3">
            <Switch id="appl_first_purchase" v-model="applicabilityForm.first_purchase_only" />
            <Label for="appl_first_purchase" class="cursor-pointer">
              First purchase only
              <span class="text-muted-foreground text-xs ml-1">(reject if customer email used any promo before)</span>
            </Label>
          </div>

          <FieldError :errors="errors.applicability" />
        </div>
      </div>
    </div>

    <!-- Submit -->
    <div class="flex justify-end gap-2">
      <Button type="button" variant="outline" @click="$router.back()">Cancel</Button>
      <Button type="submit" :disabled="loading">
        <Spinner v-if="loading" />
        {{ loading ? submitLoadingText : submitText }}
      </Button>
    </div>
  </form>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { FieldError } from "@/components/ui/field";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";

const props = defineProps({
  isCreate: { type: Boolean, default: false },
  initialData: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
  submitText: { type: String, default: "Save" },
  submitLoadingText: { type: String, default: "Saving.." },
});

const emit = defineEmits(["submit"]);

const availableTargetTypes = ["Reservation", "Order", "Ticket"];
const availableLineKeys = ["rooms", "transfer", "surcharge", "subtotal"];

const weekdays = [
  { value: 1, label: "Mon" },
  { value: 2, label: "Tue" },
  { value: 3, label: "Wed" },
  { value: 4, label: "Thu" },
  { value: 5, label: "Fri" },
  { value: 6, label: "Sat" },
  { value: 7, label: "Sun" },
];

const applicabilityForm = ref({
  events: "",
  hotels: "",
  room_types: "",
  min_nights: null,
  min_qty: null,
  guest_email_domains: "",
  weekdays: [],
  first_purchase_only: false,
});

const form = ref({
  name: "",
  slug: "",
  description: "",
  kind: "discount",
  value_type: "percentage",
  value: 0,
  value_config: {
    buy_qty: 1,
    get_free_qty: 1,
    bundle_qty: 2,
    bundle_price: 0,
    max_free_qty: 1,
    metric: "qty",
    tiers: [],
    target_line_keys: [],
  },
  max_discount_amount: null,
  min_purchase_amount: null,
  currency: "IDR",
  applies_before_tax: true,
  stacking_mode: "exclusive",
  priority: 100,
  starts_at: null,
  ends_at: null,
  is_active: true,
  target_types: [],
  applicability: null,
  trigger_type: "none",
  trigger_config: null,
  revert_usage_on_cancel: true,
  event_id: null,
  project_id: null,
});

const usesValueConfig = computed(() =>
  !["percentage", "fixed_amount"].includes(form.value.value_type),
);

function addTier() {
  if (!Array.isArray(form.value.value_config.tiers)) {
    form.value.value_config.tiers = [];
  }
  form.value.value_config.tiers.push({ min: 0, value: 0 });
}

function removeTier(idx) {
  form.value.value_config.tiers.splice(idx, 1);
}

function toggleLineKey(key, checked) {
  const set = new Set(form.value.value_config.target_line_keys ?? []);
  if (checked) set.add(key);
  else set.delete(key);
  form.value.value_config.target_line_keys = [...set];
}

const triggerWindow = ref("onsite");
const triggerStart = ref(null);
const triggerEnd = ref(null);
const triggerDays = ref(7);
const triggerOperator = ref("lt");

// Initialize from initialData if editing
if (props.initialData) {
  const defaultConfig = { ...form.value.value_config };
  Object.assign(form.value, {
    ...props.initialData,
    target_types: props.initialData.target_types ?? [],
    starts_at: props.initialData.starts_at ? new Date(props.initialData.starts_at) : null,
    ends_at: props.initialData.ends_at ? new Date(props.initialData.ends_at) : null,
    value_config: { ...defaultConfig, ...(props.initialData.value_config ?? {}) },
  });

  if (props.initialData.applicability && typeof props.initialData.applicability === "object") {
    const appl = props.initialData.applicability;
    applicabilityForm.value = {
      events: Array.isArray(appl.events) ? appl.events.join(", ") : "",
      hotels: Array.isArray(appl.hotels) ? appl.hotels.join(", ") : "",
      room_types: Array.isArray(appl.room_types) ? appl.room_types.join(", ") : "",
      min_nights: appl.min_nights ?? null,
      min_qty: appl.min_qty ?? null,
      guest_email_domains: Array.isArray(appl.guest_email_domains) ? appl.guest_email_domains.join(", ") : "",
      weekdays: Array.isArray(appl.weekdays) ? appl.weekdays.map(Number).filter(Boolean) : [],
      first_purchase_only: !!appl.first_purchase_only,
    };
  }

  if (props.initialData.trigger_config) {
    const cfg = props.initialData.trigger_config;
    if (cfg.window) triggerWindow.value = cfg.window;
    if (cfg.phase) triggerWindow.value = cfg.phase;
    if (cfg.start) triggerStart.value = new Date(cfg.start);
    if (cfg.end) triggerEnd.value = new Date(cfg.end);
    if (cfg.max_days) triggerDays.value = cfg.max_days;
    if (cfg.min_days) triggerDays.value = cfg.min_days;
    if (cfg.operator) triggerOperator.value = cfg.operator;
  }
}

// Watch kind to reset trigger_type for discount
watch(
  () => form.value.kind,
  (kind) => {
    if (kind === "discount") {
      form.value.trigger_type = "none";
    } else if (form.value.trigger_type === "none") {
      form.value.trigger_type = "manual";
    }
  },
);

function toggleTargetType(type, checked) {
  const set = new Set(form.value.target_types ?? []);
  if (checked) set.add(type);
  else set.delete(type);
  form.value.target_types = [...set];
}

function toggleWeekday(day, checked) {
  const set = new Set(applicabilityForm.value.weekdays);
  if (checked) set.add(day);
  else set.delete(day);
  applicabilityForm.value.weekdays = [...set];
}

function parseCsvIds(str) {
  if (!str || typeof str !== "string") return [];
  return str
    .split(",")
    .map((s) => Number(s.trim()))
    .filter((n) => Number.isInteger(n) && n > 0);
}

function parseCsvStrings(str) {
  if (!str || typeof str !== "string") return [];
  return str
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean);
}

function buildApplicability() {
  const out = {};
  const a = applicabilityForm.value;

  const events = parseCsvIds(a.events);
  if (events.length) out.events = events;

  const hotels = parseCsvIds(a.hotels);
  if (hotels.length) out.hotels = hotels;

  const roomTypes = parseCsvIds(a.room_types);
  if (roomTypes.length) out.room_types = roomTypes;

  if (a.min_nights && Number(a.min_nights) > 0) out.min_nights = Number(a.min_nights);
  if (a.min_qty && Number(a.min_qty) > 0) out.min_qty = Number(a.min_qty);

  const domains = parseCsvStrings(a.guest_email_domains);
  if (domains.length) out.guest_email_domains = domains;

  if (a.weekdays.length) out.weekdays = [...a.weekdays];
  if (a.first_purchase_only) out.first_purchase_only = true;

  return Object.keys(out).length ? out : null;
}

function formatDateTimeForBackend(date) {
  if (!date) return null;
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  const hours = String(date.getHours()).padStart(2, "0");
  const minutes = String(date.getMinutes()).padStart(2, "0");
  return `${year}-${month}-${day} ${hours}:${minutes}:00`;
}

function buildTriggerConfig() {
  switch (form.value.trigger_type) {
    case "booking_window":
      return { window: triggerWindow.value };
    case "event_period":
      return { phase: triggerWindow.value };
    case "date_range":
      return {
        start: formatDateTimeForBackend(triggerStart.value),
        end: formatDateTimeForBackend(triggerEnd.value),
      };
    case "lead_time":
      return { max_days: Number(triggerDays.value) || 0, operator: triggerOperator.value };
    case "cancellation_window":
      return { min_days: Number(triggerDays.value) || 0, operator: triggerOperator.value };
    default:
      return null;
  }
}

function handleSubmit() {
  const payload = { ...form.value };
  payload.target_types = payload.target_types?.length ? payload.target_types : null;
  payload.trigger_config = form.value.kind === "penalty" ? buildTriggerConfig() : null;

  payload.starts_at = formatDateTimeForBackend(form.value.starts_at);
  payload.ends_at = formatDateTimeForBackend(form.value.ends_at);

  // Empty slug = let server auto-generate
  if (!payload.slug) {
    delete payload.slug;
  }

  payload.value_config = buildValueConfig(form.value.value_type, form.value.value_config);
  payload.applicability = buildApplicability();

  emit("submit", payload);
}

function buildValueConfig(valueType, cfg) {
  if (!cfg) return null;
  switch (valueType) {
    case "buy_x_get_y":
      return { buy_qty: cfg.buy_qty, get_free_qty: cfg.get_free_qty, target_line_keys: cfg.target_line_keys?.length ? cfg.target_line_keys : undefined };
    case "tiered_percentage":
    case "tiered_fixed_amount":
      return { metric: cfg.metric ?? "qty", tiers: (cfg.tiers ?? []).map((t) => ({ min: Number(t.min) || 0, value: Number(t.value) || 0 })) };
    case "bundle_price":
      return { bundle_qty: cfg.bundle_qty, bundle_price: cfg.bundle_price, target_line_keys: cfg.target_line_keys?.length ? cfg.target_line_keys : undefined };
    case "free_addon":
      return {
        max_free_qty: cfg.max_free_qty || undefined,
        target_line_keys: cfg.target_line_keys?.length ? cfg.target_line_keys : undefined,
      };
    default:
      return null;
  }
}
</script>
