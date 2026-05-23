export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  const token = getRouterParam(event, "token");

  const baseUrl = (config.public as any).apiUrl || "http://localhost:8000";
  const apiKey = (config as any).pmOneApiKey;

  // Forward Origin so a Sessions - Components retry mints a key bound to
  // this caller's origin.
  const origin = getRequestHeader(event, "origin");

  try {
    return await $fetch(`${baseUrl}/api/public/reservations/magic/${token}/retry-payment`, {
      method: "POST",
      headers: {
        "X-API-Key": apiKey,
        ...(origin ? { Origin: origin } : {}),
      },
    });
  } catch (err: any) {
    throw createError({
      statusCode: err?.response?.status ?? 500,
      statusMessage: err?.data?.message || err?.message || "Retry failed",
      data: err?.data,
    });
  }
});
