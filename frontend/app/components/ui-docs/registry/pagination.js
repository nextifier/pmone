import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "pagination",
  title: "Pagination",
  description:
    "Composable page-number navigation. Combine PaginationFirst/Last/Previous/Next with PaginationItem and PaginationEllipsis to build any layout.",
  installation: {
    importPath: "@/components/ui/pagination",
    imports: [
      "Pagination",
      "PaginationContent",
      "PaginationFirst",
      "PaginationLast",
      "PaginationPrevious",
      "PaginationNext",
      "PaginationItem",
      "PaginationEllipsis",
    ],
  },
  whenToUse: {
    title: "When to use Pagination vs PaginationCustom",
    description:
      "Pagination gives you primitives to assemble. PaginationCustom is a pre-assembled single-component shortcut for the common case (current page + total).",
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Number bar with prev/next, first/last, and ellipsis for long ranges.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-edges",
      title: "With edges",
      description: "showEdges keeps the first and last page visible even on long ranges.",
      examples: ["with-edges"],
      align: "center",
    },
    {
      id: "controlled",
      title: "Controlled",
      description: "Bind v-model:page to a ref so the active page is reflected outside the component.",
      examples: ["controlled"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Pagination",
      props: [
        { name: "page", type: "number", default: "1", description: "Current page. Supports v-model." },
        { name: "total", type: "number", default: "0", description: "Total number of items." },
        { name: "itemsPerPage", type: "number", default: "10", description: "How many items per page." },
        { name: "siblingCount", type: "number", default: "1", description: "Sibling pages around the current page." },
        { name: "showEdges", type: "boolean", default: "false", description: "Always show first and last page numbers." },
      ],
      events: [
        { name: "update:page", description: "Fires when the page changes. Enables v-model:page." },
      ],
      slots: [
        { name: "default", description: "Scoped slot exposing { page, pageCount } to build the content row." },
      ],
    },
    {
      component: "PaginationContent / PaginationItem",
      props: [
        { name: "value", type: "number", default: "—", description: "(PaginationItem) Page number this button selects. Content lays out the items." },
      ],
    },
    {
      component: "PaginationFirst / PaginationPrevious / PaginationNext / PaginationLast",
      props: [
        { name: "class", type: "string", default: "—", description: "Edge navigation buttons. Default slot overrides the icon. Forwards to reka-ui." },
      ],
    },
    {
      component: "PaginationEllipsis",
      props: [
        { name: "class", type: "string", default: "—", description: "Placeholder shown where page numbers are collapsed." },
      ],
    },
  ],
});
