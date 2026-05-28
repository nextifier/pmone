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
    },
  ],
});
