import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "payment-method-badge",
  title: "Payment Method Badge",
  description:
    "Branded badge for credit-card networks and bank-transfer logos. Pass a method id (visa, mastercard, amex, bca, ...) and the right logo and colour render automatically.",
  installation: {
    importPath: "@/components/ui/payment-method-badge",
    imports: ["PaymentMethodBadge"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Inline-flex badge with logo + readable label.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "methods",
      title: "All methods",
      description: "Common card and bank methods rendered side by side.",
      examples: ["methods"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "PaymentMethodBadge",
      props: [
        { name: "channel", type: "string | null", default: "—", description: "Payment channel id; resolves the logo and label first (e.g. a gateway channel code)." },
        { name: "method", type: "string | null", default: "—", description: "Method id fallback (visa, mastercard, amex, jcb, bca, mandiri, bni, bri, gopay, ovo, dana, qris)." },
        { name: "size", type: '"sm" | "md" | "lg"', default: '"md"', description: "Badge height." },
        { name: "showLabel", type: "boolean", default: "true", description: "Show the text label next to the logo." },
        { name: "iconOnly", type: "boolean", default: "false", description: "Render only the logo; falls back to the label when no logo exists." },
      ],
    },
  ],
});
