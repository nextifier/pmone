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
  ],
  apiReference: [
    {
      component: "Popover",
      props: [
        { name: "open", type: "boolean", default: "—", description: "Open state. Supports v-model:open." },
        { name: "modal", type: "boolean", default: "false", description: "Trap focus and dim background." },
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
});
