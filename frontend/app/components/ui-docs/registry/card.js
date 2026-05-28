import Default from "../examples/card/default.vue";
import DefaultSrc from "../examples/card/default.vue?raw";
import WithFooter from "../examples/card/with-footer.vue";
import WithFooterSrc from "../examples/card/with-footer.vue?raw";
import Grid from "../examples/card/grid.vue";
import GridSrc from "../examples/card/grid.vue?raw";

export default {
  name: "card",
  title: "Card",
  description:
    "A container with separate header, content, and footer slots. Useful for dashboard stats, list items, or any block that needs a visual boundary.",
  installation: {
    importPath: "@/components/ui/card",
    imports: [
      "Card",
      "CardHeader",
      "CardTitle",
      "CardDescription",
      "CardContent",
      "CardFooter",
      "CardAction",
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Header with title and description, body in CardContent.",
      examples: [{ component: Default, source: DefaultSrc, align: "center" }],
    },
    {
      id: "with-footer",
      title: "With footer",
      description: "Add CardFooter to place action buttons at the bottom.",
      examples: [{ component: WithFooter, source: WithFooterSrc, align: "center" }],
    },
    {
      id: "grid",
      title: "Card grid",
      description:
        "Common dashboard pattern: a row of stats cards in a responsive grid with gap-x-2 gap-y-6.",
      examples: [{ component: Grid, source: GridSrc, align: "start" }],
    },
  ],
  apiReference: [
    {
      component: "Card",
      props: [
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Extra classes. Defaults already include rounded-xl, border, and bg-card.",
        },
      ],
    },
    {
      component: "CardHeader",
      props: [
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Top container for title, description, and optional action.",
        },
      ],
    },
    {
      component: "CardTitle / CardDescription",
      props: [
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Title is font-semibold tracking-tighter. Description uses text-muted-foreground.",
        },
      ],
    },
    {
      component: "CardContent / CardFooter",
      props: [
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Body and action area. Padding is preset.",
        },
      ],
    },
  ],
};
