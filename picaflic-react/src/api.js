const BASE = import.meta.env.VITE_API_BASE || "http://localhost:8080";

const getAT = () => localStorage.getItem("token");
const getRT = () => localStorage.getItem("refresh_token");

const setPair = (j) => {
  if (j?.token) localStorage.setItem("token", j.token);
  if (j?.refresh_token) localStorage.setItem("refresh_token", j.refresh_token);
  // notify header/App
  window.dispatchEvent(new Event("paf-auth-changed"));
};

// --- single raw() ---
async function raw(path, opts = {}) {
  const isForm = opts.body instanceof FormData;
  const headers = {
    ...(isForm ? {} : { "Content-Type": "application/json" }),
    ...(getAT() ? { Authorization: `Bearer ${getAT()}` } : {}),
    ...(opts.headers || {}),
  };

  const res = await fetch(`${BASE}${path}`, { ...opts, headers });
  const text = await res.text();
  const isJSON = (res.headers.get("content-type") || "").includes(
    "application/json"
  );
  const data = isJSON && text ? JSON.parse(text) : (isJSON ? {} : text);
  return { res, data };
}

// --- single request() with refresh-once logic ---
async function request(path, opts = {}, retried = false) {
  const { res, data } = await raw(path, opts);

  if (res.status !== 401) {
    if (!res.ok) {
      throw new Error(typeof data === "string" ? data : data?.error || res.statusText);
    }
    return data;
  }

  // 401: one-shot refresh using stored refresh token
  if (retried || !getRT()) throw new Error("Unauthorized");

  const r = await fetch(`${BASE}/auth/refresh`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ refresh_token: getRT() }),
  });

  const j = await r.json().catch(() => ({}));
  if (!r.ok || !j.token) throw new Error(j.error || "Refresh failed");
  setPair(j);

  return request(path, opts, true);
}

export const img = {
  poster: (p, w = 342) => (p ? `https://image.tmdb.org/t/p/w${w}${p}` : ""),
};

export const api = {
  base: BASE,

  // --- auth ---
  async login(email, password) {
    const j = await request("/auth/login", {
      method: "POST",
      body: JSON.stringify({ email, password }),
    });
    setPair(j);
    return j;
  },

  async register(email, password, display_name) {
    return request("/auth/register", {
      method: "POST",
      body: JSON.stringify({ email, password, display_name }),
    });
  },

  me() {
    return request("/auth/me");
  },

  forgot(email) {
    return request("/auth/forgot", {
      method: "POST",
      body: JSON.stringify({ email }),
    });
  },

  resetPassword(token, password) {
    return request("/auth/reset", {
      method: "POST",
      body: JSON.stringify({ token, password }),
    });
  },

  logout() {
    const rt = localStorage.getItem("refresh_token");
    localStorage.removeItem("token");
    localStorage.removeItem("refresh_token");
    window.dispatchEvent(new Event("paf-auth-changed"));
    if (rt) {
      // fire-and-forget
      fetch(`${BASE}/auth/logout`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ refresh_token: rt }),
      });
    }
  },

  // --- feed/search ---
  deck(params = {}) {
    const qs = new URLSearchParams(params).toString();
    return request(`/feed/deck?${qs}`);
  },

  forYou(params = {}) {
    const qs = new URLSearchParams(params).toString();
    return request(`/feed/for-you?${qs}`);
  },

  challenge(params = {}) {
    const qs = new URLSearchParams(params).toString();
    return request(`/feed/challenge-me?${qs}`);
  },

  search({ q, limit = 60, page = 1, providers, type } = {}) {
    const qs = new URLSearchParams({
      q: q || "",
      limit: String(limit),
      page: String(page),
      ...(providers ? { providers } : {}),
      ...(type ? { type } : {}),
    }).toString();
    return request(`/search?${qs}`);
  },

  // --- optional details ---
  providers({ tmdb_id, is_tv, region = "US" }) {
    const kind = is_tv ? "tv" : "movie";
    return request(
      `/title/${kind}/${tmdb_id}/providers?region=${encodeURIComponent(region)}`
    );
  },

  // --- social/profile stubs (wire when backend ready) ---
  updateServices(services) {
    return request("/profile/services", {
      method: "POST",
      body: JSON.stringify({ services }),
    });
  },
  friends() {
    return request("/profile/friends");
  },
  likes() {
    return request("/profile/likes");
  },
  watchlist() {
    return request("/profile/watchlist");
  },

  swipe(movie_id, liked) {
    return request("/social/swipe", {
      method: "POST",
      body: JSON.stringify({ movie_id, liked }),
    });
  },

  matches(friendId, params) {
    const qs = new URLSearchParams(params || {}).toString();
    return request(`/social/matches/${friendId}${qs ? `?${qs}` : ""}`);
  },
};
