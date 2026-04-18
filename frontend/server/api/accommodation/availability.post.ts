export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  const body = await readBody(event);

  const baseUrl = (config.public as any).apiUrl || "http://localhost:8000";
  const apiKey = (config as any).pmOneApiKey;

  return await $fetch(`${baseUrl}/api/public/hotels/availability`, {
    method: "POST",
    headers: { "X-API-Key": apiKey },
    body,
  });
});
