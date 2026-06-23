import type { NextConfig } from "next";

// In production, nginx serves this app on a *.milejet.space subdomain and
// proxies /api + /sanctum to the Laravel app on the same host — same-origin,
// no CORS, and the .milejet.space session cookie just works.
//
// For local dev, API_PROXY_TARGET lets `next dev` proxy API calls so the
// browser sees a single origin. Note: the Laravel session cookie is scoped
// to .milejet.space, so a host-file alias (dev.milejet.space → 127.0.0.1)
// works where plain localhost will not hold the session.
const apiProxyTarget = process.env.API_PROXY_TARGET; // e.g. https://hr.milejet.space

const nextConfig: NextConfig = {
  async rewrites() {
    if (!apiProxyTarget) return [];
    return [
      { source: "/api/:path*", destination: `${apiProxyTarget}/api/:path*` },
      { source: "/sanctum/:path*", destination: `${apiProxyTarget}/sanctum/:path*` },
    ];
  },
};

export default nextConfig;
