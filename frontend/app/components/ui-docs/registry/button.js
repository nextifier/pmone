import Default from "../examples/button/default.vue";
import DefaultSrc from "../examples/button/default.vue?raw";
import Variants from "../examples/button/variants.vue";
import VariantsSrc from "../examples/button/variants.vue?raw";
import Sizes from "../examples/button/sizes.vue";
import SizesSrc from "../examples/button/sizes.vue?raw";
import WithIcon from "../examples/button/with-icon.vue";
import WithIconSrc from "../examples/button/with-icon.vue?raw";
import AsLink from "../examples/button/as-link.vue";
import AsLinkSrc from "../examples/button/as-link.vue?raw";
import Disabled from "../examples/button/disabled.vue";
import DisabledSrc from "../examples/button/disabled.vue?raw";
import Loading from "../examples/button/loading.vue";
import LoadingSrc from "../examples/button/loading.vue?raw";

export default {
  name: "button",
  title: "Button",
  description:
    "Triggers an action. Seven visual variants and five sizes. Renders as a NuxtLink when the to prop is set.",
  installation: {
    importPath: "@/components/ui/button",
    imports: ["Button"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Primary action button.",
      examples: [{ component: Default, source: DefaultSrc, align: "center" }],
    },
    {
      id: "variants",
      title: "Variants",
      description:
        "Default for primary actions, secondary as a companion, outline for neutral, destructive for destructive actions, ghost for toolbars, link for inline text.",
      examples: [{ component: Variants, source: VariantsSrc, align: "center" }],
    },
    {
      id: "sizes",
      title: "Sizes",
      description: "sm (h-8), default (h-9), lg (h-10), iconSm (size-8), icon (size-9).",
      examples: [{ component: Sizes, source: SizesSrc, align: "center" }],
    },
    {
      id: "with-icon",
      title: "With icon",
      description: "Drop an Icon inside the default slot. Horizontal padding adjusts on its own.",
      examples: [{ component: WithIcon, source: WithIconSrc, align: "center" }],
    },
    {
      id: "as-link",
      title: "As a link",
      description:
        "Set the to prop with an internal path or external URL. URLs starting with http open in a new tab with rel noopener.",
      examples: [{ component: AsLink, source: AsLinkSrc, align: "center" }],
    },
    {
      id: "disabled",
      title: "Disabled",
      description: "The disabled attribute drops opacity and blocks pointer events.",
      examples: [{ component: Disabled, source: DisabledSrc, align: "center" }],
    },
    {
      id: "loading",
      title: "Loading",
      description:
        "There is no built-in loading prop. Combine disabled with a spinning Icon (hugeicons:loading-03).",
      examples: [{ component: Loading, source: LoadingSrc, align: "center" }],
    },
  ],
  apiReference: [
    {
      component: "Button",
      props: [
        {
          name: "variant",
          type: '"default" | "secondary" | "outline" | "outline-destructive" | "destructive" | "ghost" | "link"',
          default: '"default"',
          description: "Visual style.",
        },
        {
          name: "size",
          type: '"sm" | "default" | "lg" | "icon" | "iconSm"',
          default: '"default"',
          description: "Height and padding.",
        },
        {
          name: "to",
          type: "string",
          default: "—",
          description:
            "Renders as NuxtLink when set. External URLs (http) open in a new tab.",
        },
        {
          name: "as",
          type: "string",
          default: '"button"',
          description: "HTML element to render when to is empty.",
        },
        {
          name: "asChild",
          type: "boolean",
          default: "false",
          description: "Render the child slot without a wrapper element.",
        },
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Extra classes, merged with cn().",
        },
      ],
    },
  ],
};
