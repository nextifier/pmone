export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  const token = getRouterParam(event, "token");

  const baseUrl = (config.public as any).apiUrl || "http://localhost:8000";
  const apiKey = (config as any).pmOneApiKey;

  // Forward the Origin header so a pending Sessions - Components reservation
  // can mint a fresh SDK key bound to this caller's origin.
  const origin = getRequestHeader(event, "origin");

  return await $fetch(`${baseUrl}/api/public/reservations/magic/${token}`, {
    headers: {
      "X-API-Key": apiKey,
      ...(origin ? { Origin: origin } : {}),
    },
  });
});
