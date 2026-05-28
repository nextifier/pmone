import Default from "../examples/table/default.vue";
import DefaultSrc from "../examples/table/default.vue?raw";
import WithBadge from "../examples/table/with-badge.vue";
import WithBadgeSrc from "../examples/table/with-badge.vue?raw";
import Empty from "../examples/table/empty.vue";
import EmptySrc from "../examples/table/empty.vue?raw";

export default {
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
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Table with Header and Body.",
      examples: [{ component: Default, source: DefaultSrc, align: "start" }],
    },
    {
      id: "with-badge",
      title: "Status with Badge",
      description: "Common pattern: status column uses Badge plain so it does not dominate the row.",
      examples: [{ component: WithBadge, source: WithBadgeSrc, align: "start" }],
    },
    {
      id: "empty",
      title: "Empty state",
      description: "Use TableEmpty when there is no data. The colspan must match the column count.",
      examples: [{ component: Empty, source: EmptySrc, align: "start" }],
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
  ],
};
