import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "avatar",
  title: "Avatar",
  description:
    "User profile image with initials fallback, colourful mesh background, and status indicator. Pass a model object with name and optional profile_image to render.",
  installation: {
    importPath: "@/components/ui/avatar",
    imports: ["Avatar"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Auto-generates initials and a hashed mesh-gradient background from the name.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-image",
      title: "With image",
      description: "Pass a profile_image map keyed by size (sm, md, lg).",
      examples: ["with-image"],
      align: "center",
    },
    {
      id: "indicator",
      title: "Status indicator",
      description: "Add a coloured dot for presence or status.",
      examples: ["indicator"],
      align: "center",
    },
    {
      id: "rounding",
      title: "Rounding",
      description: "circle for full round, rounded prop for any Tailwind rounded class.",
      examples: ["rounding"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Avatar",
      props: [
        {
          name: "model",
          type: "{ name?: string, profile_image?: Record<string, string> }",
          default: "—",
          description: "User-like object. Falls back to initials when profile_image is missing.",
        },
        {
          name: "size",
          type: '"sm" | "md" | "lg"',
          default: '"sm"',
          description: "Key used to pick the URL from profile_image.",
        },
        {
          name: "rounded",
          type: "string",
          default: '"rounded-lg"',
          description: "Tailwind rounded class. Use squircle for icon-style avatars.",
        },
        { name: "circle", type: "boolean", default: "false", description: "Force rounded-full." },
        {
          name: "colorful",
          type: "boolean",
          default: "true",
          description: "Enable the auto-generated mesh-gradient background for initials.",
        },
        {
          name: "gradientFrame",
          type: "boolean",
          default: "false",
          description: "Instagram-style rotating gradient frame around the avatar.",
        },
        {
          name: "indicator",
          type: '"success" | "info" | "warning" | "destructive" | "primary"',
          default: "null",
          description: "Coloured dot in the bottom-right corner.",
        },
        { name: "noTooltip", type: "boolean", default: "false", description: "Disable name tooltip when inside AvatarGroup." },
      ],
    },
  ],
});
