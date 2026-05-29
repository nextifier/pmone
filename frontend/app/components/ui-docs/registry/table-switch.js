import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "table-switch",
  title: "Table Switch",
  description:
    "Switch sized for use inside TableData rows. Tracks state in a shared useState keyed by itemId + statusKey so multiple instances on the same page stay in sync.",
  installation: { importPath: "@/components/ui/table-switch", imports: ["TableSwitch"] },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Drop into a row cell. itemId is required so each row has its own state.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "TableSwitch",
      props: [
        { name: "modelValue", type: "boolean", default: "—", description: "Checked state. Supports v-model." },
        {
          name: "itemId",
          type: "string | number",
          default: "—",
          description: "Required. Stable id of the row. Used as part of the shared-state key.",
        },
        {
          name: "statusKey",
          type: "string",
          default: '"default"',
          description: "Namespace for the shared state. Use one per status column when you have multiple toggles per row.",
        },
        { name: "disabled", type: "boolean", default: "false", description: "Disable input." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires with the new checked state. Enables v-model." },
      ],
    },
  ],
});
