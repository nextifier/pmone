import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "chart-composed",
  title: "Composed Chart",
  description:
    "Composed charts overlay more than one geometry on the same axes. ChartComposed draws a filled area behind a line, useful for forecast or target zones.",
  installation: {
    importPath: "@/components/ui/chart",
    imports: ["ChartComposed"],
  },
  sections: [
    {
      id: "forecast",
      title: "Forecast",
      description: "A striped area zone behind a natural line, with a legend.",
      examples: ["forecast"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "ChartComposed",
      props: [
        { name: "data", type: "Record<string, any>[]", default: "—", description: "Series rows." },
        { name: "config", type: "ChartConfig", default: "—", description: "Map of key → { label, color }." },
        { name: "xKey", type: "string", default: "\"month\"", description: "Row key for the category axis." },
        { name: "areaKey", type: "string", default: "\"area\"", description: "Row key plotted as the filled area." },
        { name: "lineKey", type: "string", default: "\"value\"", description: "Row key plotted as the line." },
        { name: "areaFill", type: "string", default: "\"var(--chart-1)\"", description: "Area fill (often a url(#pattern))." },
        { name: "svgDefs", type: "string", default: "null", description: "Raw SVG <defs> string (patterns/gradients)." },
        { name: "curveType", type: "string", default: "\"natural\"", description: "Curve interpolation." },
        { name: "legend", type: "boolean", default: "false", description: "Render a legend below the chart." },
      ],
    },
  ],
});
