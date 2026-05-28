import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "status-indicator",
  title: "Status Indicator",
  description:
    "Colour-coded dot with a status label. Maps a string status to a colour and animates a slow ping ring. Hide the label for a pure dot.",
  installation: { importPath: "@/components/ui/status-indicator", imports: ["StatusIndicator"] },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Dot plus label, colour derived from the status string.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "variants",
      title: "Status values",
      description:
        "Available statuses: Available (green), Coming soon / Upcoming / Maintenance (yellow), Sold out / Unavailable (red). Toggle show-status-label off for a pure dot.",
      examples: ["variants"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "StatusIndicator",
      props: [
        {
          name: "status",
          type: "string",
          default: "—",
          description:
            "Required. Status string; colour is derived from the value (case-insensitive). See variants section for the recognised values.",
        },
        {
          name: "showStatusLabel",
          type: "boolean",
          default: "true",
          description: "Show the status text next to the dot. Set false for a bare indicator.",
        },
      ],
    },
  ],
});
