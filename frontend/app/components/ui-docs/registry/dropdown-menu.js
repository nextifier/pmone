import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "dropdown-menu",
  title: "Dropdown Menu",
  description:
    "Click-triggered menu attached to a button. Supports items, separators, labels, shortcuts, checkbox and radio items, and nested submenus.",
  installation: {
    importPath: "@/components/ui/dropdown-menu",
    imports: [
      "DropdownMenu",
      "DropdownMenuTrigger",
      "DropdownMenuContent",
      "DropdownMenuItem",
      "DropdownMenuLabel",
      "DropdownMenuSeparator",
      "DropdownMenuShortcut",
      "DropdownMenuGroup",
      "DropdownMenuCheckboxItem",
      "DropdownMenuRadioGroup",
      "DropdownMenuRadioItem",
      "DropdownMenuSub",
      "DropdownMenuSubTrigger",
      "DropdownMenuSubContent",
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Trigger button + items with separators.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "checkbox",
      title: "Checkbox items",
      description: "Toggle persisted state inside a menu.",
      examples: ["checkbox"],
      align: "center",
    },
    {
      id: "radio",
      title: "Radio items",
      description: "One-of-many selection within a menu.",
      examples: ["radio"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "DropdownMenu",
      props: [
        { name: "open", type: "boolean", default: "—", description: "Open state. Supports v-model:open." },
        { name: "modal", type: "boolean", default: "true", description: "Trap focus and dim background when open." },
      ],
    },
    {
      component: "DropdownMenuContent",
      props: [
        { name: "side", type: '"top" | "right" | "bottom" | "left"', default: '"bottom"', description: "Anchor side." },
        { name: "align", type: '"start" | "center" | "end"', default: '"center"', description: "Alignment along the side." },
        { name: "sideOffset", type: "number", default: "4", description: "Gap from the trigger in px." },
      ],
    },
  ],
});
