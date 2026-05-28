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
  ],
  apiReference: [
    {
      component: "ContextMenuItem",
      props: [
        { name: "disabled", type: "boolean", default: "false", description: "Block selection." },
        { name: "inset", type: "boolean", default: "false", description: "Extra left padding to align with sibling items that have an icon." },
      ],
    },
    {
      component: "ContextMenuCheckboxItem / ContextMenuRadioItem",
      props: [
        { name: "modelValue", type: "boolean | string", default: "—", description: "Item state. Supports v-model." },
      ],
    },
  ],
});
