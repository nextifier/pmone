import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "popover",
  title: "Popover",
  description:
    "Floating panel anchored to a trigger, opened on click. For hover-triggered panels use HoverCard; for menus use DropdownMenu.",
  installation: {
    importPath: "@/components/ui/popover",
    imports: ["Popover", "PopoverTrigger", "PopoverContent"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Click trigger to open.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-form",
      title: "With form",
      description: "Common pattern: small inline form inside a popover.",
      examples: ["with-form"],
      align: "center",
    },
    {
      id: "sides",
      title: "Sides",
      description: "Set side to top, right, bottom, or left on the content.",
      examples: ["sides"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Popover",
      props: [
        { name: "open", type: "boolean", default: "—", description: "Open state. Supports v-model:open." },
        { name: "modal", type: "boolean", default: "false", description: "Trap focus and dim background." },
      ],
      events: [
        { name: "update:open", description: "Fires when the popover opens or closes. Enables v-model:open." },
      ],
    },
    {
      component: "PopoverTrigger",
      props: [
        { name: "asChild", type: "boolean", default: "false", description: "Merge props onto the child element instead of a button." },
      ],
    },
    {
      component: "PopoverContent",
      props: [
        { name: "side", type: '"top" | "right" | "bottom" | "left"', default: '"bottom"', description: "Anchor side." },
        { name: "align", type: '"start" | "center" | "end"', default: '"center"', description: "Alignment along the side." },
        { name: "sideOffset", type: "number", default: "4", description: "Gap from the trigger." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Space"], description: "When focus is on the trigger, toggles the popover open or closed." },
      { keys: ["Enter"], description: "When focus is on the trigger, toggles the popover open or closed." },
      { keys: ["Tab"], description: "Moves through focusable elements inside the content, then out of the popover." },
      { keys: ["Shift", "Tab"], description: "Moves focus to the previous focusable element." },
      { keys: ["Esc"], description: "Closes the popover and returns focus to the trigger." },
    ],
    notes: [
      "Focus moves into the content when the popover opens.",
      "The trigger exposes aria-expanded and aria-controls linking it to the content.",
    ],
  },
});
