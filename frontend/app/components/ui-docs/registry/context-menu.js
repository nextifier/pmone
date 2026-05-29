import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "context-menu",
  title: "Context Menu",
  description:
    "Right-click menu. Same shape as DropdownMenu but triggered by contextmenu events instead of a button. Supports nested submenus and checkbox/radio items.",
  installation: {
    importPath: "@/components/ui/context-menu",
    imports: [
      "ContextMenu",
      "ContextMenuTrigger",
      "ContextMenuContent",
      "ContextMenuItem",
      "ContextMenuLabel",
      "ContextMenuSeparator",
      "ContextMenuShortcut",
      "ContextMenuCheckboxItem",
      "ContextMenuRadioGroup",
      "ContextMenuRadioItem",
      "ContextMenuSub",
      "ContextMenuSubTrigger",
      "ContextMenuSubContent",
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Right-click the trigger to open.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-submenu",
      title: "With submenu",
      description: "Nest related actions in a submenu that opens on hover or right arrow.",
      examples: ["with-submenu"],
      align: "center",
    },
    {
      id: "checkbox-radio",
      title: "Checkbox and radio items",
      description: "Toggle options with checkbox items and pick one from a radio group.",
      examples: ["checkbox-radio"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "ContextMenu",
      props: [
        { name: "open", type: "boolean", default: "—", description: "Open state. Supports v-model:open." },
      ],
      events: [
        { name: "update:open", description: "Fires when the menu opens or closes." },
      ],
    },
    {
      component: "ContextMenuTrigger",
      props: [
        { name: "disabled", type: "boolean", default: "false", description: "Stop the contextmenu event from opening the menu." },
      ],
      slots: [
        { name: "default", description: "The region that responds to right-click." },
      ],
    },
    {
      component: "ContextMenuContent",
      props: [
        { name: "class", type: "string", default: "—", description: "Extra classes. Forwards positioning props to reka-ui ContextMenuContent." },
      ],
      events: [
        { name: "close-auto-focus", description: "Fires when focus returns after the menu closes." },
      ],
    },
    {
      component: "ContextMenuItem",
      props: [
        { name: "disabled", type: "boolean", default: "false", description: "Block selection." },
        { name: "inset", type: "boolean", default: "false", description: "Extra left padding to align with sibling items that have an icon." },
      ],
      events: [
        { name: "select", description: "Fires when the item is chosen." },
      ],
    },
    {
      component: "ContextMenuCheckboxItem / ContextMenuRadioItem",
      props: [
        { name: "modelValue", type: "boolean | string", default: "—", description: "Item state. Supports v-model." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires when the checkbox toggles or the radio value changes." },
      ],
    },
    {
      component: "ContextMenuSub / ContextMenuSubTrigger / ContextMenuSubContent",
      props: [
        { name: "inset", type: "boolean", default: "false", description: "(SubTrigger) Extra left padding to align with icon items. Submenu opens on hover/right-arrow." },
      ],
    },
    {
      component: "ContextMenuLabel",
      props: [
        { name: "inset", type: "boolean", default: "false", description: "Align the non-interactive section label with inset items." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Shift", "F10"], description: "Opens the context menu from the keyboard (alongside the Menu key)." },
      { keys: ["↑"], description: "Moves focus to the previous item." },
      { keys: ["↓"], description: "Moves focus to the next item." },
      { keys: ["→"], description: "Opens a submenu when focus is on a submenu trigger." },
      { keys: ["←"], description: "Closes a submenu and returns focus to its trigger." },
      { keys: ["Esc"], description: "Closes the menu." },
      { keys: ["A"], description: "Typing characters jumps to the matching item (typeahead)." },
    ],
    notes: [
      "Opens via the contextmenu event (right-click, Shift+F10, or the Menu key).",
      "Shares the same menu semantics as DropdownMenu (roving tabindex, role=menuitem) but is contextmenu-triggered.",
    ],
  },
});
