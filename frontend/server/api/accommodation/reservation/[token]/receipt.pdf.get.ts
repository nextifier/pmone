export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  const token = getRouterParam(event, "token");

  const baseUrl = (config.public as any).apiUrl || "http://localhost:8000";
  const apiKey = (config as any).pmOneApiKey;

  const buffer = await $fetch<ArrayBuffer>(
    `${baseUrl}/api/public/reservations/magic/${token}/receipt.pdf`,
    {
      headers: { "X-API-Key": apiKey },
      responseType: "arrayBuffer",
    },
  );

  setHeader(event, "Content-Type", "application/pdf");
  setHeader(event, "Content-Disposition", `inline; filename="receipt-${token}.pdf"`);

  return Buffer.from(buffer);
});
