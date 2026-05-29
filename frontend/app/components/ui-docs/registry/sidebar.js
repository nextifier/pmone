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
  anatomy: {
    tree: [
      { component: "SidebarProvider", children: [
        { component: "Sidebar", children: [
          { component: "SidebarHeader" },
          { component: "SidebarContent", children: [
            { component: "SidebarGroup", children: [
              { component: "SidebarGroupLabel" },
              { component: "SidebarGroupContent", children: [
                { component: "SidebarMenu", children: [
                  { component: "SidebarMenuItem", children: [ { component: "SidebarMenuButton" } ] },
                ]},
              ]},
            ]},
          ]},
          { component: "SidebarFooter" },
          { component: "SidebarRail" },
        ]},
        { component: "SidebarInset" },
      ]},
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
      events: [
        { name: "update:open", description: "Fires when the sidebar opens or collapses. Enables v-model:open." },
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
    {
      component: "SidebarTrigger / SidebarRail",
      props: [
        { name: "class", type: "string", default: "—", description: "Trigger is the toggle button; Rail is the draggable edge that also toggles on click." },
      ],
    },
    {
      component: "SidebarMenuButton",
      props: [
        { name: "isActive", type: "boolean", default: "false", description: "Mark the current nav item." },
        { name: "tooltip", type: "string | Component", default: "—", description: "Shown when the sidebar is collapsed to icon mode." },
        { name: "variant", type: '"default" | "outline"', default: '"default"', description: "Visual style." },
        { name: "size", type: '"default" | "sm" | "lg"', default: '"default"', description: "Button height." },
        { name: "asChild", type: "boolean", default: "false", description: "Render into a child element such as NuxtLink." },
      ],
    },
    {
      component: "SidebarMenu / SidebarMenuItem / SidebarMenuAction / SidebarMenuBadge",
      props: [
        { name: "class", type: "string", default: "—", description: "Menu is the list; Item is one row; Action is a trailing button; Badge shows a count." },
      ],
    },
    {
      component: "SidebarMenuSub / SidebarMenuSubItem / SidebarMenuSubButton",
      props: [
        { name: "isActive", type: "boolean", default: "false", description: "(SubButton) Mark the active nested item. Sub renders the indented child list." },
      ],
    },
    {
      component: "SidebarHeader / SidebarContent / SidebarFooter / SidebarGroup / SidebarGroupLabel / SidebarGroupContent / SidebarGroupAction / SidebarInset / SidebarSeparator / SidebarInput / SidebarMenuSkeleton",
      props: [
        { name: "class", type: "string", default: "—", description: "Structural wrappers for the sidebar regions. SidebarInset holds the page content beside the sidebar." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Tab"], description: "Moves focus through the menu links and buttons." },
      { keys: ["Shift", "Tab"], description: "Moves focus to the previous link or button." },
      { keys: ["Enter"], description: "Activates the focused SidebarMenuButton." },
      { keys: ["Space"], description: "Activates the focused SidebarMenuButton." },
    ],
    notes: [
      "Ctrl/Cmd+B toggles the sidebar via the SIDEBAR_KEYBOARD_SHORTCUT global shortcut.",
      "Supports collapsible offcanvas and icon modes; the SidebarTrigger carries an aria-label for its toggle action.",
    ],
  },
});
