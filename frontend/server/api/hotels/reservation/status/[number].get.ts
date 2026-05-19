export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  const number = getRouterParam(event, "number");

  const baseUrl = (config.public as any).apiUrl || "http://localhost:8000";
  const apiKey = (config as any).pmOneApiKey;

  return await $fetch(`${baseUrl}/api/public/reservations/status/${number}`, {
    headers: { "X-API-Key": apiKey },
  });
});
