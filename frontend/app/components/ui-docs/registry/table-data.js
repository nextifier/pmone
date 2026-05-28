import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "table-data",
  title: "Table Data",
  description:
    "Batteries-included data table built on TanStack Table. Pass columns, data, and a paginator meta object; you get search, sort, filter, pagination, row selection, bulk actions, and column visibility out of the box. For static tables, use the primitive Table.",
  installation: { importPath: "@/components/ui/table-data", imports: ["TableData"] },
  whenToUse: {
    title: "When to use TableData vs Table",
    description:
      "Table is for static, read-only displays of small data. TableData is the full feature set when users need to search, sort, filter, paginate, or bulk-act on rows.",
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Five rows with TanStack column definitions and a Laravel-style paginator meta.",
      examples: ["default"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "TableData",
      props: [
        {
          name: "columns",
          type: "ColumnDef[]",
          default: "—",
          description:
            "TanStack Table column definitions. Use accessorKey for data fields and id for custom columns like select / actions. header and cell accept strings or render functions.",
        },
        { name: "data", type: "Row[]", default: "—", description: "Row data array." },
        {
          name: "meta",
          type: "PaginatorMeta",
          default: "—",
          description:
            "Laravel-style paginator metadata: { current_page, per_page, total, last_page, from, to }.",
        },
        { name: "model", type: "string", default: "—", description: "Resource slug used for query-string keys and add-button label." },
        { name: "label", type: "string", default: "—", description: "Singular label shown in the add button and empty state." },
        { name: "pending", type: "boolean", default: "false", description: "Show skeleton rows while data loads." },
        { name: "error", type: "Error | string | null", default: "null", description: "Render an error block above the table." },
        { name: "searchable", type: "boolean", default: "true", description: "Show the search input." },
        { name: "columnToggle", type: "boolean", default: "true", description: "Show the column visibility menu." },
        { name: "initialSorting", type: "SortingState", default: "[{ id: 'created_at', desc: true }]", description: "Initial sort. Override when your data has no created_at column." },
        { name: "initialPagination", type: "PaginationState", default: "{ pageIndex: 0, pageSize: 10 }", description: "Initial page index and size." },
      ],
      events: [
        { name: "refresh", description: "Fired when the user clicks the refresh button." },
        { name: "update:pagination", description: "Fired when the page index or size changes." },
        { name: "update:sorting", description: "Fired when sort state changes." },
        { name: "update:columnFilters", description: "Fired when column filters change." },
        { name: "update:rowSelection", description: "Fired when row selection changes." },
      ],
      slots: [
        { name: "filters", description: "Slot for custom filter UI. Receives { table }." },
        { name: "add-button", description: "Slot for a custom add button in the toolbar." },
        { name: "actions", description: "Toolbar actions on the right." },
        { name: "bulk-actions", description: "Toolbar shown when rows are selected." },
      ],
    },
  ],
});
