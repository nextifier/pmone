import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "grid-fill",
  title: "Grid Fill",
  description:
    "Responsive grid that auto-fills empty cells with a placeholder pattern, so the last row always looks complete. Use for galleries, masonry-style lists, or stat tiles.",
  installation: {
    importPath: "@/components/ui/grid-fill",
    imports: ["GridFill"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Pass count and let GridFill add fillers to complete the last row.",
      examples: ["default"],
      align: "start",
    },
    {
      id: "custom-filler",
      title: "Custom filler slot",
      description: "Use the #filler slot to render your own placeholder. Receives { index }.",
      examples: ["custom-filler"],
      align: "start",
    },
    {
      id: "rounded",
      title: "Rounded",
      description: "The rounded prop applies consistent corners with overflow hidden.",
      examples: ["rounded"],
      align: "start",
    },
    {
      id: "non-square",
      title: "Non-square items",
      description: "GridFill does not force aspect-square; items and fillers can be any height.",
      examples: ["non-square"],
      align: "start",
    },
    {
      id: "fixed-cols",
      title: "Fixed columns",
      description: "Set :min-col-width=\"false\" to disable auto-fit and lock the column count.",
      examples: ["fixed-cols"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "GridFill",
      props: [
        { name: "count", type: "number", default: "—", description: "Number of items rendered in the default slot." },
        { name: "cols", type: "number", default: "2", description: "Base column count below the responsive breakpoint." },
        { name: "breakpoint", type: '"xs" | "sm" | "md" | "lg" | "xl" | "2xl" | "3xl"', default: '"sm"', description: "Breakpoint where auto-fit takes over." },
        { name: "minColWidth", type: "string | false", default: '"180px"', description: "Min column width for auto-fit. Set false to disable auto-fit." },
        { name: "rounded", type: '"sm" | "md" | "lg" | "xl" | "2xl" | "3xl"', default: "—", description: "Rounded corners + overflow hidden." },
        { name: "fillerClass", type: "string", default: '"bg-pattern-diagonal"', description: "Class applied to each filler cell." },
      ],
      slots: [
        { name: "default", description: "Grid items." },
        { name: "filler", description: "Custom filler cell. Receives { index }." },
      ],
    },
  ],
});
