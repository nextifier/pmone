<template>
  <div class="bg-background relative h-screen w-full overflow-hidden" ref="containerRef">
    <!-- Header -->
    <div
      class="border-border container-wider fixed inset-x-0 top-0 z-20 flex hidden h-(--navbar-height-desktop) items-center justify-between border-b bg-gray-50 dark:bg-gray-900"
    >
      <h1 class="text-foreground text-base font-semibold tracking-tighter">Database Diagram</h1>
      <ColorModeToggle />
    </div>

    <!-- Table detail dialog -->
    <DialogResponsive v-model:open="detailOpen" dialog-max-width="500px" :overflow-content="true">
      <template #default>
        <div v-if="selectedTable" class="px-4 pb-10 md:px-6 md:py-5">
          <div class="mb-4">
            <h2 class="text-foreground text-lg font-semibold tracking-tight">
              {{ selectedTable.name }}
            </h2>
            <div class="mt-1.5 flex items-center gap-2">
              <Badge
                variant="outline"
                class="text-[10px]"
                :style="{
                  borderColor: getGroupForTable(selectedTable.name)?.color,
                  color: getGroupForTable(selectedTable.name)?.color,
                }"
              >
                {{ getGroupForTable(selectedTable.name)?.label }}
              </Badge>
              <span class="text-muted-foreground text-xs"
                >{{ Object.keys(selectedTable.columns).length }} columns</span
              >
            </div>
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead class="w-10 pl-2">Key</TableHead>
                <TableHead>Column</TableHead>
                <TableHead class="pr-2 text-right">Type</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow
                v-for="(type, col) in selectedTable.columns"
                :key="col"
                :class="
                  isForeignKey(selectedTable.name, col)
                    ? 'bg-blue-600/5'
                    : isPrimaryKey(col)
                      ? 'bg-amber-600/5'
                      : ''
                "
              >
                <TableCell class="py-1.5 pr-0 pl-2">
                  <Badge
                    v-if="isPrimaryKey(col)"
                    variant="outline"
                    class="border-amber-600/50 px-1 py-0 text-[9px] text-amber-600"
                    >PK</Badge
                  >
                  <Badge
                    v-else-if="isForeignKey(selectedTable.name, col)"
                    variant="outline"
                    class="border-blue-600/50 px-1 py-0 text-[9px] text-blue-600"
                    >FK</Badge
                  >
                </TableCell>
                <TableCell class="py-1.5 font-mono text-xs">{{ col }}</TableCell>
                <TableCell class="text-muted-foreground py-1.5 pr-2 text-right font-mono text-xs">{{
                  type
                }}</TableCell>
              </TableRow>
            </TableBody>
          </Table>

          <template v-if="selectedTable.foreign_keys.length > 0">
            <Separator class="my-4" />
            <h3 class="text-muted-foreground mb-2 text-xs font-semibold tracking-wider uppercase">
              Foreign Keys
            </h3>
            <div class="space-y-1.5">
              <div
                v-for="fk in selectedTable.foreign_keys"
                :key="fk.col"
                class="flex items-center gap-1.5 text-xs"
              >
                <Badge
                  variant="outline"
                  class="border-blue-600/50 px-1 py-0 text-[10px] text-blue-600"
                  >{{ fk.col }}</Badge
                >
                <svg
                  class="text-muted-foreground size-3"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M17 8l4 4m0 0l-4 4m4-4H3"
                  />
                </svg>
                <button @click="navigateToTable(fk.ref_table)" class="text-primary hover:underline">
                  {{ fk.ref_table }}
                </button>
                <span class="text-muted-foreground">.{{ fk.ref_col }}</span>
              </div>
            </div>
          </template>

          <template v-if="getReferencedBy(selectedTable.name).length > 0">
            <Separator class="my-4" />
            <h3 class="text-muted-foreground mb-2 text-xs font-semibold tracking-wider uppercase">
              Referenced By
            </h3>
            <div class="space-y-1.5">
              <div
                v-for="ref in getReferencedBy(selectedTable.name)"
                :key="`${ref.table}-${ref.col}`"
                class="flex items-center gap-1.5 text-xs"
              >
                <button @click="navigateToTable(ref.table)" class="text-primary hover:underline">
                  {{ ref.table }}
                </button>
                <span class="text-muted-foreground">.{{ ref.col }}</span>
              </div>
            </div>
          </template>
        </div>
      </template>
    </DialogResponsive>

    <!-- SVG Canvas -->
    <svg
      ref="svgRef"
      class="h-full w-full"
      :class="isDragging ? 'cursor-grabbing' : 'cursor-grab'"
      @mousedown="startPan"
      @mousemove="onPan"
      @mouseup="endPan"
      @mouseleave="endPan"
      @wheel="onWheel"
    >
      <g :transform="`translate(${panX}, ${panY}) scale(${zoom})`">
        <!-- Relationship lines -->
        <g v-for="rel in visibleRelationships" :key="`${rel.from}-${rel.fromCol}-${rel.to}`">
          <path
            :d="rel.path"
            fill="none"
            :stroke="
              highlightedTable === rel.from || highlightedTable === rel.to
                ? 'var(--color-info)'
                : 'var(--border)'
            "
            :stroke-width="highlightedTable === rel.from || highlightedTable === rel.to ? 1.5 : 0.8"
            stroke-dasharray="4,3"
          />
          <circle
            :cx="rel.toX"
            :cy="rel.toY"
            r="3"
            :fill="
              highlightedTable === rel.from || highlightedTable === rel.to
                ? 'var(--color-info)'
                : 'var(--muted-foreground)'
            "
          />
        </g>

        <!-- Table boxes -->
        <g
          v-for="table in visibleTables"
          :key="table.name"
          :transform="`translate(${table.x}, ${table.y})`"
          @click.stop="onTableClick(table)"
          @mouseenter="highlightedTable = table.name"
          @mouseleave="highlightedTable = null"
          class="cursor-pointer"
        >
          <rect
            :width="tableWidth"
            :height="getTableHeight(table)"
            rx="6"
            fill="var(--card)"
            :stroke="
              selectedTable?.name === table.name
                ? 'var(--color-info)'
                : highlightedTable === table.name
                  ? 'var(--muted-foreground)'
                  : 'var(--border)'
            "
            stroke-width="1"
          />
          <rect
            :width="tableWidth"
            height="28"
            rx="6"
            :fill="getGroupForTable(table.name)?.color || 'var(--muted-foreground)'"
            opacity="0.15"
          />
          <rect
            x="0"
            y="22"
            :width="tableWidth"
            height="6"
            :fill="getGroupForTable(table.name)?.color || 'var(--muted-foreground)'"
            opacity="0.15"
          />
          <line
            x1="0"
            y1="28"
            :x2="tableWidth"
            y2="28"
            :stroke="getGroupForTable(table.name)?.color || 'var(--muted-foreground)'"
            opacity="0.4"
            stroke-width="1"
          />
          <text
            x="10"
            y="18"
            :fill="getGroupForTable(table.name)?.color || 'var(--card-foreground)'"
            font-size="11"
            font-weight="600"
            font-family="ui-monospace, monospace"
          >
            {{ table.name }}
          </text>
          <text
            :x="tableWidth - 10"
            y="18"
            fill="var(--muted-foreground)"
            font-size="9"
            font-family="ui-monospace, monospace"
            text-anchor="end"
          >
            {{ Object.keys(table.columns).length }}
          </text>
          <g v-for="(col, idx) in getDisplayColumns(table)" :key="col.name">
            <text
              :x="10"
              :y="44 + idx * 18"
              fill="var(--card-foreground)"
              font-size="10"
              font-family="ui-monospace, monospace"
            >
              <tspan v-if="col.isPk" fill="var(--color-amber-600)">{{ col.name }}</tspan>
              <tspan v-else-if="col.isFk" fill="var(--color-blue-600)">{{ col.name }}</tspan>
              <tspan v-else>{{ col.name }}</tspan>
            </text>
            <text
              :x="tableWidth - 10"
              :y="44 + idx * 18"
              fill="var(--muted-foreground)"
              font-size="9"
              font-family="ui-monospace, monospace"
              text-anchor="end"
            >
              {{ col.type }}
            </text>
          </g>
        </g>

        <!-- Group labels -->
        <g v-for="gl in groupLabels" :key="gl.key">
          <text
            :x="gl.x"
            :y="gl.y - 10"
            :fill="gl.color"
            font-size="11"
            font-weight="600"
            font-family="system-ui, sans-serif"
            opacity="0.9"
          >
            {{ gl.label }}
          </text>
        </g>
      </g>
    </svg>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

definePageMeta({ layout: "empty" });
usePageMeta(null, { title: "Database Diagram" });

const containerRef = ref(null);
const svgRef = ref(null);

const zoom = ref(0.75);
const panX = ref(60);
const panY = ref(80);
const isDragging = ref(false);
const dragStart = ref({ x: 0, y: 0 });
const selectedTable = ref(null);
const highlightedTable = ref(null);
const searchQuery = ref("");
const activeGroup = ref("all");

const detailOpen = computed({
  get: () => !!selectedTable.value,
  set: (v) => {
    if (!v) selectedTable.value = null;
  },
});

const tableWidth = 210;
const tableGapX = 250;
const tableGapY = 30;

const groups = [
  { key: "users", label: "Users & Auth", color: "#f59e0b" },
  { key: "projects", label: "Projects & Tasks", color: "#10b981" },
  { key: "events", label: "Events & Brands", color: "#8b5cf6" },
  { key: "content", label: "Content", color: "#ec4899" },
  { key: "orders", label: "Orders", color: "#ef4444" },
  { key: "forms", label: "Forms", color: "#0ea5e9" },
  { key: "analytics", label: "API & Analytics", color: "#14b8a6" },
];

const tableGroupMap = {
  users: "users",
  roles: "users",
  permissions: "users",
  model_has_roles: "users",
  model_has_permissions: "users",
  role_has_permissions: "users",

  projects: "projects",
  project_user: "projects",
  project_custom_fields: "projects",
  tasks: "projects",
  task_user: "projects",

  events: "events",
  event_conjunctions: "events",
  event_products: "events",
  event_product_categories: "events",
  event_documents: "events",
  event_document_submissions: "events",
  brands: "events",
  brand_event: "events",
  brand_user: "events",
  promotion_posts: "events",

  posts: "content",
  post_authors: "content",
  post_autosaves: "content",
  tags: "content",
  taggables: "content",
  categories: "content",
  category_post: "content",
  links: "content",
  short_links: "content",
  contact_form_submissions: "content",
  notifications: "content",

  orders: "orders",
  order_items: "orders",
  exchange_rates: "orders",

  forms: "forms",
  form_fields: "forms",
  form_responses: "forms",

  api_consumers: "analytics",
  api_consumer_requests: "analytics",
  ga_properties: "analytics",
  analytics_metrics: "analytics",
  analytics_sync_logs: "analytics",
  clicks: "analytics",
  visits: "analytics",
};

// Business logic tables only
const rawTables = [
  // ── Users & Auth ──
  {
    name: "users",
    columns: {
      id: "int8",
      ulid: "bpchar",
      name: "varchar",
      username: "varchar",
      email: "varchar",
      email_verified_at: "timestamp",
      password: "varchar",
      title: "varchar",
      phone: "varchar",
      company_name: "varchar",
      birth_date: "date",
      gender: "varchar",
      bio: "text",
      user_settings: "jsonb",
      more_details: "jsonb",
      custom_fields: "jsonb",
      status: "varchar",
      visibility: "varchar",
      last_seen: "timestamp",
      created_at: "timestamp",
      updated_at: "timestamp",
      deleted_at: "timestamp",
      created_by: "int8",
      updated_by: "int8",
      deleted_by: "int8",
    },
    foreign_keys: [
      { col: "created_by", ref_table: "users", ref_col: "id" },
      { col: "deleted_by", ref_table: "users", ref_col: "id" },
      { col: "updated_by", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "roles",
    columns: {
      id: "int8",
      name: "varchar",
      guard_name: "varchar",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [],
  },
  {
    name: "permissions",
    columns: {
      id: "int8",
      name: "varchar",
      guard_name: "varchar",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [],
  },
  {
    name: "model_has_roles",
    columns: { role_id: "int8", model_type: "varchar", model_id: "int8" },
    foreign_keys: [{ col: "role_id", ref_table: "roles", ref_col: "id" }],
  },
  {
    name: "model_has_permissions",
    columns: { permission_id: "int8", model_type: "varchar", model_id: "int8" },
    foreign_keys: [],
  },
  {
    name: "role_has_permissions",
    columns: { permission_id: "int8", role_id: "int8" },
    foreign_keys: [
      { col: "permission_id", ref_table: "permissions", ref_col: "id" },
      { col: "role_id", ref_table: "roles", ref_col: "id" },
    ],
  },

  // ── Projects & Tasks ──
  {
    name: "projects",
    columns: {
      id: "int8",
      ulid: "bpchar",
      name: "varchar",
      username: "varchar",
      bio: "text",
      settings: "json",
      more_details: "json",
      status: "varchar",
      visibility: "varchar",
      email: "varchar",
      phone: "json",
      order_column: "int4",
      created_at: "timestamp",
      updated_at: "timestamp",
      deleted_at: "timestamp",
      created_by: "int8",
      updated_by: "int8",
      deleted_by: "int8",
    },
    foreign_keys: [
      { col: "created_by", ref_table: "users", ref_col: "id" },
      { col: "deleted_by", ref_table: "users", ref_col: "id" },
      { col: "updated_by", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "project_user",
    columns: {
      project_id: "int8",
      user_id: "int8",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [
      { col: "project_id", ref_table: "projects", ref_col: "id" },
      { col: "user_id", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "project_custom_fields",
    columns: {
      id: "int8",
      project_id: "int8",
      label: "varchar",
      key: "varchar",
      type: "varchar",
      options: "jsonb",
      is_required: "bool",
      order_column: "int4",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [{ col: "project_id", ref_table: "projects", ref_col: "id" }],
  },
  {
    name: "tasks",
    columns: {
      id: "int8",
      ulid: "bpchar",
      title: "text",
      description: "text",
      status: "varchar",
      priority: "varchar",
      complexity: "varchar",
      visibility: "varchar",
      project_id: "int8",
      assignee_id: "int8",
      estimated_start_at: "timestamp",
      estimated_completion_at: "timestamp",
      completed_at: "timestamp",
      order_column: "int4",
      created_by: "int8",
      updated_by: "int8",
      deleted_by: "int8",
      created_at: "timestamp",
      updated_at: "timestamp",
      deleted_at: "timestamp",
    },
    foreign_keys: [
      { col: "assignee_id", ref_table: "users", ref_col: "id" },
      { col: "created_by", ref_table: "users", ref_col: "id" },
      { col: "deleted_by", ref_table: "users", ref_col: "id" },
      { col: "project_id", ref_table: "projects", ref_col: "id" },
      { col: "updated_by", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "task_user",
    columns: {
      id: "int8",
      task_id: "int8",
      user_id: "int8",
      role: "varchar",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [
      { col: "task_id", ref_table: "tasks", ref_col: "id" },
      { col: "user_id", ref_table: "users", ref_col: "id" },
    ],
  },

  // ── Events & Brands ──
  {
    name: "events",
    columns: {
      id: "int8",
      ulid: "bpchar",
      project_id: "int8",
      title: "varchar",
      slug: "varchar",
      edition_number: "int4",
      description: "text",
      start_date: "timestamp",
      end_date: "timestamp",
      location: "varchar",
      location_link: "varchar",
      hall: "varchar",
      saleable_area: "numeric",
      is_active: "bool",
      status: "varchar",
      visibility: "varchar",
      settings: "jsonb",
      custom_fields: "jsonb",
      order_column: "int4",
      order_form_content: "text",
      order_form_deadline: "timestamp",
      promotion_post_deadline: "timestamp",
      normal_order_opens_at: "timestamp",
      normal_order_closes_at: "timestamp",
      onsite_order_opens_at: "timestamp",
      onsite_order_closes_at: "timestamp",
      onsite_penalty_rate: "numeric",
      badge_vip_info: "text",
      created_by: "int8",
      updated_by: "int8",
      deleted_by: "int8",
      created_at: "timestamp",
      updated_at: "timestamp",
      deleted_at: "timestamp",
    },
    foreign_keys: [
      { col: "created_by", ref_table: "users", ref_col: "id" },
      { col: "deleted_by", ref_table: "users", ref_col: "id" },
      { col: "project_id", ref_table: "projects", ref_col: "id" },
      { col: "updated_by", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "event_conjunctions",
    columns: {
      id: "int8",
      event_id: "int8",
      conjunction_event_id: "int8",
      conjunction_label: "varchar",
      order_column: "int4",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [
      { col: "conjunction_event_id", ref_table: "events", ref_col: "id" },
      { col: "event_id", ref_table: "events", ref_col: "id" },
    ],
  },
  {
    name: "event_products",
    columns: {
      id: "int8",
      event_id: "int8",
      category_id: "int8",
      name: "varchar",
      description: "text",
      price: "numeric",
      unit: "varchar",
      booth_types: "jsonb",
      is_active: "bool",
      order_column: "int4",
      created_by: "int8",
      updated_by: "int8",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [
      { col: "category_id", ref_table: "event_product_categories", ref_col: "id" },
      { col: "created_by", ref_table: "users", ref_col: "id" },
      { col: "event_id", ref_table: "events", ref_col: "id" },
      { col: "updated_by", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "event_product_categories",
    columns: {
      id: "int8",
      event_id: "int8",
      title: "varchar",
      slug: "varchar",
      description: "text",
      order_column: "int4",
      created_by: "int8",
      updated_by: "int8",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [
      { col: "created_by", ref_table: "users", ref_col: "id" },
      { col: "event_id", ref_table: "events", ref_col: "id" },
      { col: "updated_by", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "event_documents",
    columns: {
      id: "int8",
      ulid: "bpchar",
      event_id: "int8",
      title: "varchar",
      slug: "varchar",
      description: "text",
      document_type: "varchar",
      is_required: "bool",
      blocks_next_step: "bool",
      submission_deadline: "timestamp",
      booth_types: "jsonb",
      order_column: "int4",
      settings: "jsonb",
      content_version: "int4",
      content_updated_at: "timestamp",
      created_by: "int8",
      updated_by: "int8",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [
      { col: "created_by", ref_table: "users", ref_col: "id" },
      { col: "event_id", ref_table: "events", ref_col: "id" },
      { col: "updated_by", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "event_document_submissions",
    columns: {
      id: "int8",
      ulid: "bpchar",
      event_document_id: "int8",
      booth_identifier: "varchar",
      event_id: "int8",
      agreed_at: "timestamp",
      text_value: "text",
      document_version: "int4",
      submitted_by: "int8",
      submitted_at: "timestamp",
      ip_address: "varchar",
      user_agent: "text",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [
      { col: "event_document_id", ref_table: "event_documents", ref_col: "id" },
      { col: "event_id", ref_table: "events", ref_col: "id" },
      { col: "submitted_by", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "brands",
    columns: {
      id: "int8",
      ulid: "bpchar",
      name: "varchar",
      slug: "varchar",
      description: "text",
      company_name: "varchar",
      company_address: "text",
      company_email: "varchar",
      company_phone: "varchar",
      custom_fields: "jsonb",
      status: "varchar",
      visibility: "varchar",
      order_column: "int4",
      created_by: "int8",
      updated_by: "int8",
      deleted_by: "int8",
      created_at: "timestamp",
      updated_at: "timestamp",
      deleted_at: "timestamp",
    },
    foreign_keys: [
      { col: "created_by", ref_table: "users", ref_col: "id" },
      { col: "deleted_by", ref_table: "users", ref_col: "id" },
      { col: "updated_by", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "brand_event",
    columns: {
      id: "int8",
      brand_id: "int8",
      event_id: "int8",
      booth_number: "varchar",
      booth_size: "numeric",
      booth_type: "varchar",
      booth_price: "numeric",
      fascia_name: "varchar",
      badge_name: "varchar",
      sales_id: "int8",
      status: "varchar",
      notes: "text",
      custom_fields: "jsonb",
      promotion_post_limit: "int4",
      order_column: "int4",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [
      { col: "brand_id", ref_table: "brands", ref_col: "id" },
      { col: "event_id", ref_table: "events", ref_col: "id" },
      { col: "sales_id", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "brand_user",
    columns: {
      id: "int8",
      brand_id: "int8",
      user_id: "int8",
      role: "varchar",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [
      { col: "brand_id", ref_table: "brands", ref_col: "id" },
      { col: "user_id", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "promotion_posts",
    columns: {
      id: "int8",
      caption: "text",
      custom_fields: "jsonb",
      order_column: "int4",
      brand_event_id: "int8",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [{ col: "brand_event_id", ref_table: "brand_event", ref_col: "id" }],
  },

  // ── Content ──
  {
    name: "posts",
    columns: {
      id: "int8",
      ulid: "bpchar",
      title: "varchar",
      slug: "varchar",
      excerpt: "text",
      content: "text",
      content_format: "varchar",
      meta_title: "varchar",
      meta_description: "text",
      status: "varchar",
      visibility: "varchar",
      published_at: "timestamp",
      featured: "bool",
      reading_time: "int4",
      settings: "json",
      source: "varchar",
      created_at: "timestamp",
      updated_at: "timestamp",
      deleted_at: "timestamp",
      created_by: "int8",
      updated_by: "int8",
      deleted_by: "int8",
    },
    foreign_keys: [
      { col: "created_by", ref_table: "users", ref_col: "id" },
      { col: "deleted_by", ref_table: "users", ref_col: "id" },
      { col: "updated_by", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "post_authors",
    columns: {
      id: "int8",
      post_id: "int8",
      user_id: "int8",
      order: "int4",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [
      { col: "post_id", ref_table: "posts", ref_col: "id" },
      { col: "user_id", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "post_autosaves",
    columns: {
      id: "int8",
      post_id: "int8",
      user_id: "int8",
      title: "varchar",
      excerpt: "text",
      content: "text",
      content_format: "varchar",
      meta_title: "varchar",
      meta_description: "text",
      status: "varchar",
      visibility: "varchar",
      published_at: "timestamp",
      featured: "bool",
      reading_time: "int4",
      settings: "json",
      tmp_media: "json",
      tags: "json",
      authors: "json",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [],
  },
  {
    name: "categories",
    columns: {
      id: "int8",
      name: "varchar",
      slug: "varchar",
      description: "text",
      visibility: "varchar",
      parent_id: "int8",
      order: "int4",
      created_by: "int8",
      updated_by: "int8",
      deleted_by: "int8",
      created_at: "timestamp",
      updated_at: "timestamp",
      deleted_at: "timestamp",
    },
    foreign_keys: [
      { col: "created_by", ref_table: "users", ref_col: "id" },
      { col: "deleted_by", ref_table: "users", ref_col: "id" },
      { col: "parent_id", ref_table: "categories", ref_col: "id" },
      { col: "updated_by", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "category_post",
    columns: {
      id: "int8",
      category_id: "int8",
      post_id: "int8",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [
      { col: "category_id", ref_table: "categories", ref_col: "id" },
      { col: "post_id", ref_table: "posts", ref_col: "id" },
    ],
  },
  {
    name: "tags",
    columns: {
      id: "int8",
      name: "json",
      slug: "json",
      type: "varchar",
      order_column: "int4",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [],
  },
  {
    name: "taggables",
    columns: { tag_id: "int8", taggable_type: "varchar", taggable_id: "int8" },
    foreign_keys: [{ col: "tag_id", ref_table: "tags", ref_col: "id" }],
  },
  {
    name: "links",
    columns: {
      id: "int8",
      linkable_type: "varchar",
      linkable_id: "int8",
      label: "varchar",
      url: "text",
      order: "int4",
      is_active: "bool",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [],
  },
  {
    name: "short_links",
    columns: {
      id: "int8",
      user_id: "int8",
      slug: "varchar",
      destination_url: "text",
      og_title: "varchar",
      og_description: "text",
      og_image: "varchar",
      og_type: "varchar",
      is_active: "bool",
      created_at: "timestamp",
      updated_at: "timestamp",
      deleted_at: "timestamp",
      created_by: "int8",
      updated_by: "int8",
      deleted_by: "int8",
    },
    foreign_keys: [
      { col: "created_by", ref_table: "users", ref_col: "id" },
      { col: "deleted_by", ref_table: "users", ref_col: "id" },
      { col: "updated_by", ref_table: "users", ref_col: "id" },
      { col: "user_id", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "contact_form_submissions",
    columns: {
      id: "int8",
      ulid: "bpchar",
      project_id: "int8",
      form_data: "json",
      subject: "varchar",
      status: "varchar",
      followed_up_at: "timestamp",
      followed_up_by: "int8",
      ip_address: "varchar",
      user_agent: "text",
      created_at: "timestamp",
      updated_at: "timestamp",
      deleted_at: "timestamp",
      deleted_by: "int8",
    },
    foreign_keys: [
      { col: "deleted_by", ref_table: "users", ref_col: "id" },
      { col: "followed_up_by", ref_table: "users", ref_col: "id" },
      { col: "project_id", ref_table: "projects", ref_col: "id" },
    ],
  },
  {
    name: "notifications",
    columns: {
      id: "uuid",
      type: "varchar",
      notifiable_type: "varchar",
      notifiable_id: "int8",
      data: "text",
      read_at: "timestamp",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [],
  },

  // ── Orders ──
  {
    name: "orders",
    columns: {
      id: "int8",
      ulid: "bpchar",
      brand_event_id: "int8",
      order_number: "varchar",
      operational_status: "varchar",
      payment_status: "varchar",
      order_period: "varchar",
      notes: "text",
      subtotal: "numeric",
      discount_type: "varchar",
      discount_value: "numeric",
      discount_amount: "numeric",
      tax_rate: "numeric",
      tax_amount: "numeric",
      total: "numeric",
      applied_penalty_rate: "numeric",
      cancellation_reason: "text",
      submitted_at: "timestamp",
      confirmed_at: "timestamp",
      created_by: "int8",
      updated_by: "int8",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [
      { col: "brand_event_id", ref_table: "brand_event", ref_col: "id" },
      { col: "created_by", ref_table: "users", ref_col: "id" },
      { col: "updated_by", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "order_items",
    columns: {
      id: "int8",
      order_id: "int8",
      event_product_id: "int8",
      product_name: "varchar",
      product_category: "varchar",
      unit_price: "numeric",
      quantity: "int4",
      total_price: "numeric",
      notes: "text",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [
      { col: "event_product_id", ref_table: "event_products", ref_col: "id" },
      { col: "order_id", ref_table: "orders", ref_col: "id" },
    ],
  },
  {
    name: "exchange_rates",
    columns: {
      id: "int8",
      base_currency: "varchar",
      rates: "json",
      api_updated_at: "timestamp",
      fetched_at: "timestamp",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [],
  },

  // ── Forms ──
  {
    name: "forms",
    columns: {
      id: "int8",
      ulid: "bpchar",
      title: "varchar",
      description: "text",
      slug: "varchar",
      settings: "json",
      status: "varchar",
      is_active: "bool",
      opens_at: "timestamp",
      closes_at: "timestamp",
      response_limit: "int4",
      project_id: "int8",
      user_id: "int8",
      created_by: "int8",
      updated_by: "int8",
      deleted_by: "int8",
      created_at: "timestamp",
      updated_at: "timestamp",
      deleted_at: "timestamp",
    },
    foreign_keys: [
      { col: "created_by", ref_table: "users", ref_col: "id" },
      { col: "deleted_by", ref_table: "users", ref_col: "id" },
      { col: "project_id", ref_table: "projects", ref_col: "id" },
      { col: "updated_by", ref_table: "users", ref_col: "id" },
      { col: "user_id", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "form_fields",
    columns: {
      id: "int8",
      ulid: "bpchar",
      form_id: "int8",
      type: "varchar",
      label: "varchar",
      placeholder: "varchar",
      help_text: "text",
      options: "json",
      validation: "json",
      settings: "json",
      order_column: "int4",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [{ col: "form_id", ref_table: "forms", ref_col: "id" }],
  },
  {
    name: "form_responses",
    columns: {
      id: "int8",
      ulid: "bpchar",
      form_id: "int8",
      response_data: "json",
      respondent_email: "varchar",
      browser_fingerprint: "varchar",
      ip_address: "varchar",
      user_agent: "text",
      status: "varchar",
      submitted_at: "timestamp",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [{ col: "form_id", ref_table: "forms", ref_col: "id" }],
  },

  // ── API & Analytics ──
  {
    name: "api_consumers",
    columns: {
      id: "int8",
      ulid: "bpchar",
      name: "varchar",
      description: "text",
      website_url: "varchar",
      api_key: "varchar",
      allowed_origins: "json",
      rate_limit: "int4",
      filters: "json",
      is_active: "bool",
      last_used_at: "timestamp",
      created_at: "timestamp",
      updated_at: "timestamp",
      deleted_at: "timestamp",
      created_by: "int8",
      updated_by: "int8",
      deleted_by: "int8",
    },
    foreign_keys: [
      { col: "created_by", ref_table: "users", ref_col: "id" },
      { col: "deleted_by", ref_table: "users", ref_col: "id" },
      { col: "updated_by", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "api_consumer_requests",
    columns: {
      id: "int8",
      api_consumer_id: "int8",
      endpoint: "varchar",
      method: "varchar",
      status_code: "int4",
      response_time_ms: "int4",
      ip_address: "varchar",
      user_agent: "varchar",
      origin: "varchar",
      created_at: "timestamp",
    },
    foreign_keys: [],
  },
  {
    name: "ga_properties",
    columns: {
      id: "int8",
      project_id: "int8",
      name: "varchar",
      property_id: "varchar",
      is_active: "bool",
      last_synced_at: "timestamp",
      sync_frequency: "int4",
      created_at: "timestamp",
      updated_at: "timestamp",
      deleted_at: "timestamp",
      created_by: "int8",
      updated_by: "int8",
      deleted_by: "int8",
    },
    foreign_keys: [
      { col: "created_by", ref_table: "users", ref_col: "id" },
      { col: "deleted_by", ref_table: "users", ref_col: "id" },
      { col: "project_id", ref_table: "projects", ref_col: "id" },
      { col: "updated_by", ref_table: "users", ref_col: "id" },
    ],
  },
  {
    name: "analytics_metrics",
    columns: {
      id: "int8",
      property_id: "varchar",
      metric_type: "varchar",
      metric_value: "int4",
      metadata: "json",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [],
  },
  {
    name: "analytics_sync_logs",
    columns: {
      id: "int8",
      sync_type: "varchar",
      ga_property_id: "int8",
      days: "int4",
      status: "varchar",
      started_at: "timestamp",
      completed_at: "timestamp",
      duration_seconds: "numeric",
      metadata: "json",
      error_message: "text",
      job_id: "varchar",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [],
  },
  {
    name: "clicks",
    columns: {
      id: "int8",
      clickable_type: "varchar",
      clickable_id: "int8",
      clicker_id: "int8",
      link_label: "varchar",
      ip_address: "varchar",
      user_agent: "text",
      referer: "text",
      clicked_at: "timestamp",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [],
  },
  {
    name: "visits",
    columns: {
      id: "int8",
      visitable_type: "varchar",
      visitable_id: "int8",
      visitor_id: "int8",
      ip_address: "varchar",
      user_agent: "text",
      referer: "text",
      visited_at: "timestamp",
      created_at: "timestamp",
      updated_at: "timestamp",
    },
    foreign_keys: [],
  },
];

// Layout: group -> columns -> rows
const groupPositions = {
  users: { startX: 0, startY: 0 },
  projects: { startX: 0, startY: 620 },
  events: { startX: 820, startY: 0 },
  orders: { startX: 820, startY: 880 },
  content: { startX: 0, startY: 1350 },
  forms: { startX: 1400, startY: 880 },
  analytics: { startX: 2150, startY: 0 },
};

const groupColumnLayouts = {
  users: [
    ["users"],
    ["roles", "permissions"],
    ["model_has_roles", "model_has_permissions", "role_has_permissions"],
  ],
  projects: [["projects"], ["project_user", "project_custom_fields"], ["tasks", "task_user"]],
  events: [
    ["events"],
    ["event_conjunctions", "event_products", "event_product_categories"],
    ["event_documents", "event_document_submissions"],
    ["brands", "brand_event"],
    ["brand_user", "promotion_posts"],
  ],
  orders: [["orders"], ["order_items", "exchange_rates"]],
  content: [
    ["posts", "contact_form_submissions"],
    ["post_authors", "post_autosaves", "categories"],
    ["category_post", "tags", "taggables", "links"],
    ["short_links", "notifications"],
  ],
  forms: [["forms"], ["form_fields", "form_responses"]],
  analytics: [
    ["api_consumers", "ga_properties"],
    ["api_consumer_requests", "analytics_metrics", "analytics_sync_logs"],
    ["clicks", "visits"],
  ],
};

const tables = computed(() => {
  const positioned = [];

  for (const [groupKey, columns] of Object.entries(groupColumnLayouts)) {
    const gPos = groupPositions[groupKey];
    let colX = gPos.startX;

    for (const column of columns) {
      let rowY = gPos.startY;

      for (const tableName of column) {
        const raw = rawTables.find((t) => t.name === tableName);
        if (!raw) continue;

        const colCount = Object.keys(raw.columns).length;
        const tableH = 34 + colCount * 18 + 8;

        positioned.push({ ...raw, x: colX, y: rowY, width: tableWidth, height: tableH });
        rowY += tableH + tableGapY;
      }

      colX += tableGapX;
    }
  }

  return positioned;
});

const groupLabels = computed(() => {
  return Object.entries(groupPositions).map(([key, pos]) => {
    const group = groups.find((g) => g.key === key);
    return {
      key,
      label: group?.label || key,
      color: group?.color || "#666",
      x: pos.startX,
      y: pos.startY,
    };
  });
});

const visibleTables = computed(() => {
  let filtered = tables.value;
  if (activeGroup.value !== "all") {
    filtered = filtered.filter((t) => tableGroupMap[t.name] === activeGroup.value);
  }
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    filtered = filtered.filter(
      (t) => t.name.includes(q) || Object.keys(t.columns).some((c) => c.includes(q))
    );
  }
  return filtered;
});

const allRelationships = computed(() => {
  const rels = [];
  for (const table of tables.value) {
    for (const fk of table.foreign_keys) {
      const target = tables.value.find((t) => t.name === fk.ref_table);
      if (!target) continue;

      const fromX = table.x + tableWidth;
      const fromY = table.y + 14;
      const toX = target.x;
      const toY = target.y + 14;

      let fx = fromX,
        fy = fromY,
        tx = toX,
        ty = toY;

      if (target.x + tableWidth < table.x) {
        fx = table.x;
        tx = target.x + tableWidth;
      } else if (Math.abs(target.x - table.x) < tableWidth) {
        if (target.y > table.y) {
          fx = table.x + tableWidth / 2;
          fy = table.y + table.height;
          tx = target.x + tableWidth / 2;
          ty = target.y;
        } else {
          fx = table.x + tableWidth / 2;
          fy = table.y;
          tx = target.x + tableWidth / 2;
          ty = target.y + target.height;
        }
      }

      const midX = (fx + tx) / 2;
      const path = `M ${fx} ${fy} C ${midX} ${fy}, ${midX} ${ty}, ${tx} ${ty}`;

      rels.push({
        from: table.name,
        fromCol: fk.col,
        to: fk.ref_table,
        toCol: fk.ref_col,
        path,
        toX: tx,
        toY: ty,
      });
    }
  }
  return rels;
});

const visibleRelationships = computed(() => {
  const visNames = new Set(visibleTables.value.map((t) => t.name));
  return allRelationships.value.filter((r) => visNames.has(r.from) && visNames.has(r.to));
});

function getGroupForTable(name) {
  const groupKey = tableGroupMap[name];
  return groups.find((g) => g.key === groupKey);
}

function getTableHeight(table) {
  const colCount = Object.keys(table.columns).length;
  return 34 + colCount * 18 + 8;
}

function getDisplayColumns(table) {
  const fkCols = new Set(table.foreign_keys.map((fk) => fk.col));
  return Object.entries(table.columns).map(([name, type]) => ({
    name,
    type,
    isPk: name === "id",
    isFk: fkCols.has(name),
  }));
}

function isPrimaryKey(col) {
  return col === "id";
}

function isForeignKey(tableName, col) {
  const table = rawTables.find((t) => t.name === tableName);
  return table?.foreign_keys.some((fk) => fk.col === col);
}

function getReferencedBy(tableName) {
  const refs = [];
  for (const table of rawTables) {
    for (const fk of table.foreign_keys) {
      if (fk.ref_table === tableName) {
        refs.push({ table: table.name, col: fk.col });
      }
    }
  }
  return refs;
}

function onTableClick(table) {
  selectedTable.value = rawTables.find((t) => t.name === table.name);
}

function navigateToTable(name) {
  selectedTable.value = null;
  nextTick(() => {
    selectedTable.value = rawTables.find((t) => t.name === name);
    highlightedTable.value = name;
  });
}

function startPan(e) {
  isDragging.value = true;
  dragStart.value = { x: e.clientX - panX.value, y: e.clientY - panY.value };
}

function onPan(e) {
  if (!isDragging.value) return;
  panX.value = e.clientX - dragStart.value.x;
  panY.value = e.clientY - dragStart.value.y;
}

function endPan() {
  isDragging.value = false;
}

function onWheel(e) {
  if (e.ctrlKey || e.metaKey) {
    e.preventDefault();
    const delta = e.deltaY > 0 ? -0.05 : 0.05;
    zoom.value = Math.max(0.2, Math.min(2, zoom.value + delta));
  } else {
    e.preventDefault();
    panX.value -= e.deltaX;
    panY.value -= e.deltaY;
  }
}

function zoomIn() {
  zoom.value = Math.min(2, zoom.value + 0.1);
}

function zoomOut() {
  zoom.value = Math.max(0.2, zoom.value - 0.1);
}

function resetView() {
  zoom.value = 0.75;
  panX.value = 60;
  panY.value = 80;
  selectedTable.value = null;
  highlightedTable.value = null;
  searchQuery.value = "";
  activeGroup.value = "all";
}
</script>
