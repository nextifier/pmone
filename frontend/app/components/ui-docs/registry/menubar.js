import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "menubar",
  title: "Menubar",
  description:
    "Persistent application menu bar (File, Edit, View ...). Each top-level item opens a DropdownMenu-style panel. Use for desktop-app-style UIs.",
  installation: {
    importPath: "@/components/ui/menubar",
    imports: [
      "Menubar",
      "MenubarMenu",
      "MenubarTrigger",
      "MenubarContent",
      "MenubarItem",
      "MenubarLabel",
      "MenubarSeparator",
      "MenubarShortcut",
      "MenubarGroup",
      "MenubarCheckboxItem",
      "MenubarRadioGroup",
      "MenubarRadioItem",
      "MenubarSub",
      "MenubarSubTrigger",
      "MenubarSubContent",
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "File-Edit-View bar with shortcuts.",
      examples: ["default"],
      align: "start",
    },
    {
      id: "with-checkbox",
      title: "With checkbox items",
      description: "Toggle view options with checkbox items bound to v-model.",
      examples: ["with-checkbox"],
      align: "start",
    },
    {
      id: "with-submenu",
      title: "With submenu",
      description: "Nest related actions in a submenu that opens on hover.",
      examples: ["with-submenu"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "Menubar",
      props: [
        { name: "modelValue", type: "string", default: "—", description: "Open menu value. Supports v-model." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires when the active top-level menu changes." },
      ],
    },
    {
      component: "MenubarMenu / MenubarTrigger",
      props: [
        { name: "value", type: "string", default: "—", description: "(MenubarMenu) Identifier for this menu. Trigger opens its panel." },
      ],
    },
    {
      component: "MenubarContent",
      props: [
        { name: "class", type: "string", default: "—", description: "Extra classes. Forwards positioning props to reka-ui MenubarContent." },
      ],
    },
    {
      component: "MenubarItem",
      props: [
        { name: "disabled", type: "boolean", default: "false", description: "Block selection." },
        { name: "inset", type: "boolean", default: "false", description: "Extra left padding to align with icon items." },
      ],
      events: [
        { name: "select", description: "Fires when the item is chosen." },
      ],
    },
    {
      component: "MenubarCheckboxItem / MenubarRadioItem",
      props: [
        { name: "modelValue", type: "boolean | string", default: "—", description: "Item state. Supports v-model." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires when the checkbox toggles or the radio value changes." },
      ],
    },
    {
      component: "MenubarSub / MenubarSubTrigger / MenubarLabel",
      props: [
        { name: "inset", type: "boolean", default: "false", description: "Align SubTrigger or Label with icon items. Submenu opens on hover or right-arrow." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["←"], description: "Moves focus to the previous top-level menu trigger." },
      { keys: ["→"], description: "Moves focus to the next top-level menu trigger." },
      { keys: ["↑"], description: "Moves to the previous item within an open menu." },
      { keys: ["↓"], description: "Opens the focused menu, or moves to the next item within an open menu." },
      { keys: ["Enter"], description: "Opens a menu or activates the focused item." },
      { keys: ["Space"], description: "Opens a menu or activates the focused item." },
      { keys: ["Esc"], description: "Closes the open menu and returns focus to its trigger." },
    ],
    notes: [
      "Uses role=menubar with a roving tabindex across the top-level triggers.",
      "Right/left arrows step into and out of submenus while a menu is open.",
    ],
  },
});
