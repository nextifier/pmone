import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "item",
  title: "Item",
  description:
    "List row primitive. Composes ItemMedia, ItemContent (with ItemTitle and ItemDescription), ItemActions, and ItemFooter into a flexible row that fits navigation lists, settings, and contact cards.",
  installation: {
    importPath: "@/components/ui/item",
    imports: ["Item", "ItemMedia", "ItemContent", "ItemTitle", "ItemDescription", "ItemActions", "ItemFooter", "ItemSeparator", "ItemGroup", "ItemHeader"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Media + content + actions.",
      examples: ["default"],
      align: "start",
    },
    {
      id: "list",
      title: "Stacked list",
      description: "Group items inside ItemGroup with ItemSeparator.",
      examples: ["list"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "Item",
      props: [
        { name: "variant", type: '"default" | "outline" | "muted"', default: '"default"', description: "Visual style." },
        { name: "size", type: '"default" | "sm"', default: '"default"', description: "Padding density." },
      ],
    },
    {
      component: "ItemMedia",
      props: [
        { name: "variant", type: '"default" | "icon" | "image"', default: '"default"', description: "Media presentation style." },
      ],
    },
  ],
});
