import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "pagination-custom",
  title: "Pagination Custom",
  description:
    "Single-component pagination ready to drop in. Renders first/last, prev/next, page numbers, and an ellipsis for long ranges.",
  installation: {
    importPath: "@/components/ui/pagination-custom",
    imports: ["PaginationCustom"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Just pass total, itemsPerPage, and v-model:page the current page.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "sibling-count",
      title: "Sibling count",
      description: "Increase siblingCount to show more page numbers on each side of the current page.",
      examples: ["sibling-count"],
      align: "center",
    },
    {
      id: "few-pages",
      title: "Few pages",
      description: "With a small total the bar renders every page and skips the ellipsis.",
      examples: ["few-pages"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "PaginationCustom",
      props: [
        { name: "page", type: "number", default: "1", description: "Current page. Supports v-model:page." },
        { name: "total", type: "number", default: "0", description: "Total number of items." },
        { name: "itemsPerPage", type: "number", default: "10", description: "Items per page; drives the page count." },
        { name: "siblingCount", type: "number", default: "1", description: "Page numbers shown on each side of the current page." },
      ],
      events: [
        { name: "update:page", description: "Fires when the page changes. Enables v-model:page." },
      ],
    },
  ],
});
