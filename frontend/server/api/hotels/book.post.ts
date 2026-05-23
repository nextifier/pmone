export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  const body = await readBody(event);

  const baseUrl = (config.public as any).apiUrl || "http://localhost:8000";
  const apiKey = (config as any).pmOneApiKey;

  // Forward the calling page's Origin so the backend can include it in the
  // Xendit Sessions `components_configuration.origins` list (required when
  // the gateway uses Sessions - Components). Harmless for other flows.
  const origin = getRequestHeader(event, "origin");

  try {
    return await $fetch(`${baseUrl}/api/public/reservations`, {
      method: "POST",
      headers: {
        "X-API-Key": apiKey,
        ...(origin ? { Origin: origin } : {}),
      },
      body,
    });
  } catch (err: any) {
    throw createError({
      statusCode: err?.response?.status ?? 500,
      statusMessage: err?.data?.message || err?.message || "Reservation failed",
      data: err?.data,
    });
  }
});
