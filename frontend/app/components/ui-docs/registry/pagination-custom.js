import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "pagination-custom",
  title: "Pagination Custom",
  description:
    "Single-component pagination ready to drop in. Renders page numbers, prev/next, and an optional jump-to-page input.",
  installation: {
    importPath: "@/components/ui/pagination-custom",
    imports: ["PaginationCustom"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Just pass total, perPage, and v-model the current page.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "PaginationCustom",
      props: [
        { name: "modelValue", type: "number", default: "1", description: "Current page. Supports v-model." },
        { name: "total", type: "number", default: "0", description: "Total items." },
        { name: "perPage", type: "number", default: "10", description: "Items per page." },
        { name: "showJump", type: "boolean", default: "false", description: "Show a Go-to-page input." },
      ],
    },
  ],
});
