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
      events: [
        { name: "update:open", description: "Fires when the menu opens or closes. Enables v-model:open." },
      ],
    },
    {
      component: "DropdownMenuTrigger",
      props: [
        { name: "asChild", type: "boolean", default: "false", description: "Merge props onto the child element instead of a button." },
      ],
    },
    {
      component: "DropdownMenuContent",
      props: [
        { name: "side", type: '"top" | "right" | "bottom" | "left"', default: '"bottom"', description: "Anchor side." },
        { name: "align", type: '"start" | "center" | "end"', default: '"center"', description: "Alignment along the side." },
        { name: "sideOffset", type: "number", default: "4", description: "Gap from the trigger in px." },
      ],
      events: [
        { name: "close-auto-focus", description: "Fires when focus returns to the trigger after closing." },
      ],
    },
    {
      component: "DropdownMenuItem",
      props: [
        { name: "disabled", type: "boolean", default: "false", description: "Block selection." },
        { name: "inset", type: "boolean", default: "false", description: "Extra left padding to align with icon items." },
      ],
      events: [
        { name: "select", description: "Fires when the item is chosen." },
      ],
    },
    {
      component: "DropdownMenuCheckboxItem / DropdownMenuRadioItem",
      props: [
        { name: "modelValue", type: "boolean | string", default: "—", description: "Item state. Supports v-model." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires when the checkbox toggles or the radio value changes." },
      ],
    },
    {
      component: "DropdownMenuSub / DropdownMenuSubTrigger / DropdownMenuSubContent",
      props: [
        { name: "inset", type: "boolean", default: "false", description: "(SubTrigger) Align with icon items. Submenu opens on hover or right-arrow." },
      ],
    },
    {
      component: "DropdownMenuLabel",
      props: [
        { name: "inset", type: "boolean", default: "false", description: "Align the section label with inset items." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Space"], description: "Opens the menu from the trigger, or activates the focused item." },
      { keys: ["Enter"], description: "Opens the menu from the trigger, or activates the focused item." },
      { keys: ["↑"], description: "Moves focus to the previous item." },
      { keys: ["↓"], description: "Moves focus to the next item." },
      { keys: ["→"], description: "Opens a submenu when focus is on a submenu trigger." },
      { keys: ["←"], description: "Closes a submenu and returns focus to its trigger." },
      { keys: ["Home"], description: "Moves focus to the first item." },
      { keys: ["End"], description: "Moves focus to the last item." },
      { keys: ["Esc"], description: "Closes the menu and returns focus to the trigger." },
      { keys: ["A"], description: "Typing characters jumps to the matching item (typeahead)." },
    ],
    notes: [
      "Uses a roving tabindex: only one item is tabbable and arrows move focus between items.",
      "Menu items use role=menuitem within a role=menu container.",
    ],
  },
});
