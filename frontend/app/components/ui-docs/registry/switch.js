import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "switch",
  title: "Switch",
  description: "On/off toggle. Use for binary settings where the result is immediate (no Save button needed).",
  installation: { importPath: "@/components/ui/switch", imports: ["Switch"] },
  sections: [
    { id: "default", title: "Default", description: "Bound with v-model.", examples: ["default"], align: "center" },
    { id: "with-label", title: "With label", description: "Pair with a Label for click targets.", examples: ["with-label"], align: "center" },
    { id: "disabled", title: "Disabled", description: "Disable input.", examples: ["disabled"], align: "center" },
  ],
  apiReference: [
    {
      component: "Switch",
      props: [
        { name: "modelValue", type: "boolean", default: "false", description: "Checked state. Supports v-model." },
        { name: "defaultValue", type: "boolean", default: "false", description: "Initial state when v-model is not used." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable input." },
        { name: "id", type: "string", default: "—", description: "Match with a Label for prop." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires with the new checked state. Enables v-model." },
      ],
      slots: [
        { name: "thumb", description: "Override the sliding thumb content (e.g. an icon)." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Space"], description: "Toggles the switch when focused." },
      { keys: ["Enter"], description: "Toggles the switch when focused." },
      { keys: ["Tab"], description: "Moves focus to or from the switch." },
    ],
    notes: [
      "Uses role=switch with aria-checked reflecting the on/off state.",
      "Pair with a label so the control's purpose is announced.",
    ],
  },
});
