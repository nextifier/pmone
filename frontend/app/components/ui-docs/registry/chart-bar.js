import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "chart-bar",
  title: "Bar Chart",
  description:
    "Bar charts built on unovis-vue. One ChartBar covers vertical/horizontal orientation, single or grouped series, and stacking.",
  installation: {
    importPath: "@/components/ui/chart",
    imports: ["ChartBar", "ChartBarInteractive"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "A single-series vertical bar chart.",
      examples: ["default"],
      align: "start",
    },
    {
      id: "horizontal",
      title: "Horizontal",
      description: "Flip the orientation with the horizontal prop.",
      examples: ["horizontal"],
      align: "start",
    },
    {
      id: "multiple",
      title: "Multiple",
      description: "Grouped bars for multiple series.",
      examples: ["multiple"],
      align: "start",
    },
    {
      id: "stacked",
      title: "Stacked",
      description: "Stack multiple series with the stacked prop.",
      examples: ["stacked"],
      align: "start",
    },
    {
      id: "interactive",
      title: "Interactive",
      description: "Toggle between series; totals update in the header.",
      examples: ["interactive"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "ChartBar",
      props: [
        { name: "data", type: "Record<string, any>[]", default: "—", description: "Series rows." },
        { name: "config", type: "ChartConfig", default: "—", description: "Map of key → { label, color }." },
        { name: "dataKey", type: "string", default: "\"value\"", description: "Single-series row key." },
        { name: "dataKeys", type: "string[]", default: "null", description: "Multi-series row keys (overrides dataKey)." },
        { name: "xKey", type: "string", default: "\"date\"", description: "Row key for the category axis." },
        { name: "horizontal", type: "boolean", default: "false", description: "Render horizontal bars." },
        { name: "stacked", type: "boolean", default: "false", description: "Stack multi-series bars." },
        { name: "legend", type: "boolean", default: "false", description: "Render a legend below the chart." },
        { name: "roundedCorners", type: "number", default: "6", description: "Bar corner radius." },
        { name: "xTickFormatter", type: "(d) => string", default: "null", description: "Custom category tick formatter." },
        { name: "yTickFormatter", type: "(d) => string", default: "null", description: "Custom value tick formatter." },
      ],
    },
    {
      component: "ChartBarInteractive",
      props: [
        { name: "data", type: "Record<string, any>[]", default: "—", description: "Series rows." },
        { name: "config", type: "ChartConfig", default: "—", description: "Theming map; series with a color become toggles." },
        { name: "dataKeys", type: "string[]", default: "null", description: "Override which series are selectable." },
        { name: "xKey", type: "string", default: "\"date\"", description: "Row key for the category axis." },
        { name: "title", type: "string", default: "\"\"", description: "Optional header title." },
        { name: "description", type: "string", default: "\"\"", description: "Optional header description." },
      ],
    },
  ],
});
