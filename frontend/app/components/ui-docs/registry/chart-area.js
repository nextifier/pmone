import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "chart-area",
  title: "Area Chart",
  description:
    "Area charts built on unovis-vue. One ChartArea handles single or multi-series data, with optional gradient fills and stacking.",
  installation: {
    importPath: "@/components/ui/chart",
    imports: ["ChartArea", "ChartAreaInteractive"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "A single-series gradient area.",
      examples: ["default"],
      align: "start",
    },
    {
      id: "gradient",
      title: "Gradient",
      description: "Multiple overlapping series, each with its own gradient fill.",
      examples: ["gradient"],
      align: "start",
    },
    {
      id: "stacked",
      title: "Stacked",
      description: "Series stacked on top of each other (stacked prop).",
      examples: ["stacked"],
      align: "start",
    },
    {
      id: "axes",
      title: "Axes",
      description: "Solid multi-series fills with both axes labelled.",
      examples: ["axes"],
      align: "start",
    },
    {
      id: "icons",
      title: "Legend & icons",
      description: "Add a legend and per-series icons via the config.",
      examples: ["icons"],
      align: "start",
    },
    {
      id: "interactive",
      title: "Interactive",
      description: "Filter the visible range with a select.",
      examples: ["interactive"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "ChartArea",
      props: [
        { name: "data", type: "Record<string, any>[]", default: "—", description: "Series rows." },
        { name: "config", type: "ChartConfig", default: "—", description: "Map of key → { label, color, icon }." },
        { name: "dataKey", type: "string", default: "\"value\"", description: "Single-series row key." },
        { name: "dataKeys", type: "string[]", default: "null", description: "Multi-series row keys (overrides dataKey)." },
        { name: "xKey", type: "string", default: "\"date\"", description: "Row key for the x value." },
        { name: "gradient", type: "boolean", default: "false", description: "Fill areas with a gradient instead of a solid color." },
        { name: "stacked", type: "boolean", default: "false", description: "Stack multi-series areas." },
        { name: "legend", type: "boolean", default: "false", description: "Render a legend below the chart." },
        { name: "numXTicks", type: "number", default: "6", description: "Approximate x-axis tick count." },
        { name: "xTickFormatter", type: "(d) => string", default: "null", description: "Custom x-axis tick formatter." },
        { name: "yTickFormatter", type: "(d) => string", default: "null", description: "Custom y-axis tick formatter." },
      ],
    },
    {
      component: "ChartAreaInteractive",
      props: [
        { name: "data", type: "Record<string, any>[]", default: "—", description: "Series rows (with a date key)." },
        { name: "config", type: "ChartConfig", default: "—", description: "Theming map." },
        { name: "dataKeys", type: "string[]", default: "null", description: "Series to plot (defaults to coloured config keys)." },
        { name: "xKey", type: "string", default: "\"date\"", description: "Row key for the x value." },
        { name: "title", type: "string", default: "\"\"", description: "Optional header title." },
        { name: "description", type: "string", default: "\"\"", description: "Optional header description." },
      ],
    },
  ],
});
