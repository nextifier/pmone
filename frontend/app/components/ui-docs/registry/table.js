import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "table",
  title: "Table",
  description:
    "Table primitives for static data. For sort, filter, search, and pagination out of the box, use TableData (documented separately).",
  installation: {
    importPath: "@/components/ui/table",
    imports: [
      "Table",
      "TableHeader",
      "TableBody",
      "TableFooter",
      "TableRow",
      "TableHead",
      "TableCell",
      "TableCaption",
      "TableEmpty",
    ],
  },
  anatomy: {
    tree: [
      { component: "Table", children: [
        { component: "TableCaption" },
        { component: "TableHeader", children: [ { component: "TableRow", children: [ { component: "TableHead" } ] } ] },
        { component: "TableBody", children: [ { component: "TableRow", children: [ { component: "TableCell" } ] } ] },
        { component: "TableFooter" },
      ]},
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Table with Header and Body.",
      examples: ["default"],
      align: "start",
    },
    {
      id: "with-badge",
      title: "Status with Badge",
      description: "Common pattern: status column uses Badge plain so it does not dominate the row.",
      examples: ["with-badge"],
      align: "start",
    },
    {
      id: "empty",
      title: "Empty state",
      description: "Use TableEmpty when there is no data. The colspan must match the column count.",
      examples: ["empty"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "Table",
      props: [
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Main container. Already wrapped in relative w-full overflow-auto.",
        },
      ],
    },
    {
      component: "TableHead / TableCell",
      props: [
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Override alignment (text-right, text-center) or width.",
        },
      ],
    },
    {
      component: "TableEmpty",
      props: [
        {
          name: "colspan",
          type: "number",
          default: "1",
          description: "Must match the number of columns in the header.",
        },
      ],
    },
    {
      component: "TableHeader / TableBody / TableFooter / TableRow / TableCaption",
      props: [
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Semantic table sections. Render the matching thead/tbody/tfoot/tr/caption with design-system styling.",
        },
      ],
    },
  ],
});
