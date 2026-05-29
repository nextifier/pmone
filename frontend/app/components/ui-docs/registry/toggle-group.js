import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "toggle-group",
  title: "Toggle Group",
  description:
    "Group of Toggles where at most one (single) or many (multiple) can be pressed at a time. Often used as a visual filter or text-alignment picker.",
  installation: {
    importPath: "@/components/ui/toggle-group",
    imports: ["ToggleGroup", "ToggleGroupItem"],
  },
  sections: [
    { id: "default", title: "Default", description: "Single-select toggle group.", examples: ["default"], align: "center" },
    { id: "multiple", title: "Multiple", description: "Allow multiple items pressed at once.", examples: ["multiple"], align: "center" },
  ],
  apiReference: [
    {
      component: "ToggleGroup",
      props: [
        { name: "type", type: '"single" | "multiple"', default: '"single"', description: "Selection mode." },
        { name: "modelValue", type: "string | string[]", default: "—", description: "Pressed value(s). Supports v-model." },
        { name: "variant", type: '"default" | "outline"', default: '"default"', description: "Same as Toggle." },
        { name: "size", type: '"sm" | "default" | "lg"', default: '"default"', description: "Same as Toggle." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires when the pressed value(s) change. Enables v-model." },
      ],
    },
    {
      component: "ToggleGroupItem",
      props: [
        { name: "value", type: "string", default: "—", description: "Unique value identifying this item." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable this item." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Tab"], description: "Moves focus into the group." },
      { keys: ["←"], description: "Moves to the previous item." },
      { keys: ["→"], description: "Moves to the next item." },
      { keys: ["↑"], description: "Moves to the previous item." },
      { keys: ["↓"], description: "Moves to the next item." },
      { keys: ["Space"], description: "Toggles the focused item." },
      { keys: ["Enter"], description: "Toggles the focused item." },
    ],
    notes: [
      "Uses a roving tabindex; only one item is in the tab sequence at a time.",
      "Single or multiple selection is conveyed via aria-pressed or aria-checked.",
    ],
  },
});
