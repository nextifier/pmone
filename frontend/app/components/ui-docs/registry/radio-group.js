import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "radio-group",
  title: "Radio Group",
  description:
    "One-of-many selection. Pair RadioGroup with RadioGroupItem entries; bind selected value with v-model.",
  installation: {
    importPath: "@/components/ui/radio-group",
    imports: ["RadioGroup", "RadioGroupItem"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Three options arranged vertically.",
      examples: ["default"],
    },
    {
      id: "horizontal",
      title: "Horizontal",
      description: "Lay items horizontally with flex.",
      examples: ["horizontal"],
    },
    {
      id: "disabled",
      title: "Disabled",
      description: "Pass disabled on the group to disable every item.",
      examples: ["disabled"],
    },
  ],
  apiReference: [
    {
      component: "RadioGroup",
      props: [
        { name: "modelValue", type: "string", default: "—", description: "Selected value. Supports v-model." },
        { name: "defaultValue", type: "string", default: "—", description: "Initial value when v-model is not used." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable the entire group." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires when the selected value changes. Enables v-model." },
      ],
    },
    {
      component: "RadioGroupItem",
      props: [
        { name: "value", type: "string", default: "—", description: "Unique value tracked by the group." },
        { name: "id", type: "string", default: "—", description: "Match with a Label for click targets." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable this item." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["↑"], description: "Moves selection to the previous item." },
      { keys: ["↓"], description: "Moves selection to the next item." },
      { keys: ["←"], description: "Moves selection to the previous item." },
      { keys: ["→"], description: "Moves selection to the next item." },
      { keys: ["Space"], description: "Selects the focused item." },
      { keys: ["Tab"], description: "Moves focus into or out of the group." },
    ],
    notes: [
      "Uses a roving tabindex; only the selected (or first) radio is tabbable.",
      "Applies radiogroup and radio roles with aria-checked on each item.",
    ],
  },
});
