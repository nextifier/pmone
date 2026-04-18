export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  const query = getQuery(event);

  const baseUrl = (config.public as any).apiUrl || "http://localhost:8000";
  const apiKey = (config as any).pmOneApiKey;

  return await $fetch(`${baseUrl}/api/public/hotels`, {
    headers: { "X-API-Key": apiKey },
    query,
  });
});
