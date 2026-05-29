import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "empty",
  title: "Empty",
  description:
    "Placeholder for lists, tables, and views that have no data yet. Compose EmptyMedia (icon or illustration), EmptyTitle, EmptyDescription, and EmptyContent for actions.",
  installation: {
    importPath: "@/components/ui/empty",
    imports: ["Empty", "EmptyHeader", "EmptyMedia", "EmptyTitle", "EmptyDescription", "EmptyContent"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Icon, title, description, and a call-to-action.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "minimal",
      title: "Minimal",
      description: "Just a title and description.",
      examples: ["minimal"],
      align: "center",
    },
    {
      id: "with-action",
      title: "With actions",
      description: "EmptyContent holds one or more call-to-action buttons.",
      examples: ["with-action"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Empty",
      props: [
        { name: "class", type: "string", default: "—", description: "Container styles." },
      ],
    },
    {
      component: "EmptyMedia",
      props: [
        {
          name: "variant",
          type: '"default" | "icon"',
          default: '"default"',
          description: "default for free-form illustrations, icon adds a circular muted background.",
        },
      ],
    },
    {
      component: "EmptyHeader / EmptyTitle / EmptyDescription / EmptyContent",
      props: [
        {
          name: "class",
          type: "string",
          default: "—",
          description:
            "Layout and typography wrappers. Header groups media + title + description; Content holds the call-to-action.",
        },
      ],
    },
  ],
});
