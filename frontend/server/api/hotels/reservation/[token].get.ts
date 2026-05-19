export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  const token = getRouterParam(event, "token");

  const baseUrl = (config.public as any).apiUrl || "http://localhost:8000";
  const apiKey = (config as any).pmOneApiKey;

  return await $fetch(`${baseUrl}/api/public/reservations/magic/${token}`, {
    headers: { "X-API-Key": apiKey },
  });
});
