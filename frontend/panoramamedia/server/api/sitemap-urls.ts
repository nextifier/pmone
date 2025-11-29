import { asSitemapUrl, defineSitemapEventHandler } from "#imports";

export default defineSitemapEventHandler(async () => {
  const posts = await $fetch<ReturnType<typeof asSitemapUrl>>(
    `${useAppConfig().app.blogApiUrl}/posts/?key=${useAppConfig().app.blogApiKey}&filter=authors.slug:[${useAppConfig().app.blogUsername}]%2Bvisibility:public&order=published_at+desc&limit=all`,
  ).then((res) => {
    return res.posts.map((post) => {
      return {
        loc: `/news/${post.slug}`,
        lastmod: post.updated_at,
      };
    });
  });

  // const brands = await $fetch<ReturnType<typeof asSitemapUrl>>(
  //   `${useAppConfig().app.apiUrl}/api/exhibitors?filter[is_published]=1`,
  // ).then((res) => {
  //   return res.map((brand) => {
  //     return {
  //       loc: `/brands/${brand.slug}`,
  //       lastmod: brand.updated_at,
  //     };
  //   });
  // });

  return [
    ...posts,
    // ...brands
  ];
});
