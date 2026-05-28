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
  ],
  apiReference: [
    {
      component: "Menubar",
      props: [
        { name: "modelValue", type: "string", default: "—", description: "Open menu value. Supports v-model." },
      ],
    },
    {
      component: "MenubarMenu",
      props: [
        { name: "value", type: "string", default: "—", description: "Identifier for this menu." },
      ],
    },
  ],
});
