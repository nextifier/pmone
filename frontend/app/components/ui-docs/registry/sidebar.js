import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "sidebar",
  title: "Sidebar",
  description:
    "Full sidebar layout primitive — handles desktop/mobile collapse, off-canvas mode, icon-only mode, and a persistent rail for resizing. The UI Library itself uses this for its left nav.",
  installation: {
    importPath: "@/components/ui/sidebar",
    imports: [
      "SidebarProvider",
      "Sidebar",
      "SidebarInset",
      "SidebarRail",
      "SidebarTrigger",
      "SidebarHeader",
      "SidebarFooter",
      "SidebarContent",
      "SidebarGroup",
      "SidebarGroupLabel",
      "SidebarGroupContent",
      "SidebarGroupAction",
      "SidebarMenu",
      "SidebarMenuItem",
      "SidebarMenuButton",
      "SidebarMenuAction",
      "SidebarMenuBadge",
      "SidebarMenuSkeleton",
      "SidebarMenuSub",
      "SidebarMenuSubItem",
      "SidebarMenuSubButton",
      "SidebarSeparator",
      "SidebarInput",
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Minimal layout: provider + Sidebar with nav + SidebarInset for the page content.",
      examples: ["default"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "SidebarProvider",
      props: [
        { name: "defaultOpen", type: "boolean", default: "true", description: "Initial open state on desktop." },
        { name: "open", type: "boolean", default: "—", description: "Open state. Supports v-model:open." },
        { name: "style", type: "object", default: "—", description: "Set CSS vars like --sidebar-width." },
      ],
    },
    {
      component: "Sidebar",
      props: [
        { name: "side", type: '"left" | "right"', default: '"left"', description: "Edge the sidebar attaches to." },
        { name: "variant", type: '"sidebar" | "floating" | "inset"', default: '"sidebar"', description: "Visual variant." },
        { name: "collapsible", type: '"offcanvas" | "icon" | "none"', default: '"offcanvas"', description: "How the sidebar collapses on mobile." },
      ],
    },
  ],
});
