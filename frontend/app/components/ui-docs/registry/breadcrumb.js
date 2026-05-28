import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "breadcrumb",
  title: "Breadcrumb",
  description:
    "Path-based navigation showing where the user is in a hierarchy. Compose with BreadcrumbList, BreadcrumbItem, BreadcrumbLink, BreadcrumbPage, and BreadcrumbSeparator.",
  installation: {
    importPath: "@/components/ui/breadcrumb",
    imports: [
      "Breadcrumb",
      "BreadcrumbList",
      "BreadcrumbItem",
      "BreadcrumbLink",
      "BreadcrumbPage",
      "BreadcrumbSeparator",
      "BreadcrumbEllipsis",
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Linked path segments with the current page as plain text.",
      examples: ["default"],
    },
    {
      id: "with-ellipsis",
      title: "With ellipsis",
      description: "Collapse long paths with BreadcrumbEllipsis.",
      examples: ["with-ellipsis"],
    },
    {
      id: "custom-separator",
      title: "Custom separator",
      description: "Override the separator slot to use any icon.",
      examples: ["custom-separator"],
    },
  ],
  apiReference: [
    {
      component: "BreadcrumbLink",
      props: [
        {
          name: "asChild",
          type: "boolean",
          default: "false",
          description: "Render into the child element. Use to wrap a NuxtLink.",
        },
      ],
    },
    {
      component: "BreadcrumbSeparator / BreadcrumbEllipsis",
      props: [
        { name: "class", type: "string", default: "—", description: "Override the default chevron or dots." },
      ],
    },
  ],
});
