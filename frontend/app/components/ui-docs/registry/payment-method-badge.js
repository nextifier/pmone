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
        { name: "method", type: "string", default: "—", description: "Method id (visa, mastercard, amex, jcb, bca, mandiri, bni, bri, gopay, ovo, dana, qris)." },
        { name: "size", type: '"sm" | "md" | "lg"', default: '"md"', description: "Badge height." },
      ],
    },
  ],
});
