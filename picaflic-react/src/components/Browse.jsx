import { useEffect, useMemo, useState } from "react";
import { api, img } from "../api";

const SERVICE_TOKENS = [
  "netflix",
  "disney",
  "hulu",
  "prime",
  "max",
  "appletv",
  "peacock",
];

export default function Browse() {
  // data + ui state
  const [rows, setRows] = useState([]);
  const [page, setPage] = useState(1);

  // search + filters
  const [query, setQuery] = useState("");
  const [showFilters, setShowFilters] = useState(false);
  const [selectedTokens, setSelectedTokens] = useState(new Set());
  const [type, setType] = useState("all"); // 'all' | 'movie' | 'tv'

  // view mode (mock prefers swipe)
  const [mode, setMode] = useState("swipe"); // 'swipe' | 'grid'
  const [idx, setIdx] = useState(0); // swipe cursor
  const current = rows[idx] || null;

  // providers for current swipe card
  const [prov, setProv] = useState([]);

  // misc
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");

  // build providers like "netflix|disney"
  const providers = useMemo(
    () => Array.from(selectedTokens).join("|"),
    [selectedTokens]
  );

  // fetch whenever page OR filters OR query change
  useEffect(() => {
    setLoading(true);
    setErr("");

    const params = { limit: 60, page };
    if (providers) params.providers = providers;
    if (type !== "all") params.type = type;
    if (query.trim()) params.q = query.trim();

    console.debug("[Browse] fetch params →", params);

    const fetcher = query.trim() ? api.search(params) : api.forYou(params);

    Promise.resolve(fetcher)
      .then((data) => {
        console.debug("[Browse] received →", {
          count: data.length,
          page,
          providers,
          type,
          q: query.trim() || null,
        });
        setRows((prev) => (page === 1 ? data : [...prev, ...data]));
      })
      .catch((e) => {
        console.debug("[Browse] error →", e);
        setErr(e.message || "Failed to load");
      })
      .finally(() => setLoading(false));
  }, [page, providers, type, query]);

  // reset swipe index when we start a fresh page-1 load
  useEffect(() => {
    if (page === 1) setIdx(0);
  }, [page, providers, type, query]);

  // fetch providers for the current swipe card
  useEffect(() => {
    let cancelled = false;
    async function loadProv() {
      if (!current) {
        if (!cancelled) setProv([]);
        return;
      }
      try {
        const p = await api.providers({
          tmdb_id: current.tmdb_id,
          is_tv: !!current.is_tv,
          region: "US",
        });
        if (!cancelled) setProv(p || []);
      } catch {
        if (!cancelled) setProv([]);
      }
    }
    loadProv();
    return () => {
      cancelled = true;
    };
  }, [current?.tmdb_id, current?.is_tv]);

  // handlers
  function onSearchSubmit(e) {
    e.preventDefault();
    setPage(1);
    setRows([]);
  }

  function clearSearch() {
    setQuery("");
    setPage(1);
    setRows([]);
  }

  function toggleProvider(token) {
    setSelectedTokens((prev) => {
      const next = new Set(prev);
      next.has(token) ? next.delete(token) : next.add(token);
      console.debug(
        "[Browse] toggle provider →",
        Array.from(next).join("|") || "(none)"
      );
      return next;
    });
    setPage(1);
    setRows([]);
  }

  function changeType(next) {
    setType(next);
    setPage(1);
    setRows([]);
  }

  function clearFilters() {
    setSelectedTokens(new Set());
    setType("all");
    setPage(1);
    setRows([]);
  }

  async function act(liked) {
    if (current) {
      // fire-and-forget; don't block UI
      api.swipe(current.tmdb_id, !!liked).catch(() => {});
    }
    const next = idx + 1;
    if (next >= rows.length - 5) setPage((p) => p + 1); // prefetch more
    setIdx(next);
  }

  // keyboard arrows for swipe
  useEffect(() => {
    const onKey = (e) => {
      if (e.key === "ArrowLeft") act(false);
      if (e.key === "ArrowRight") act(true);
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [idx, rows, current]);

  // little helper used in grid to print provider names robustly
  const joinProvNames = (list) =>
    list
      .map((p) => p.provider_name || p.name || p.display_name)
      .filter(Boolean)
      .join(", ");

  // Component that loads providers under a grid card
  function Providers({ id, isTv }) {
    const [list, setList] = useState(null); // null = loading
    useEffect(() => {
      let alive = true;
      api
        .providers({ tmdb_id: id, is_tv: !!isTv, region: "US" })
        .then((rows) => alive && setList(rows || []))
        .catch(() => alive && setList([]));
      return () => {
        alive = false;
      };
    }, [id, isTv]);

    if (list === null) return <div className="muted small">loading…</div>;
    if (!list.length) return <div className="muted small">no providers</div>;
    return <div className="muted small">{joinProvNames(list)}</div>;
  }

  return (
    <div className="stack gap">
      {/* Search bar + Filters toggle + Mode toggle */}
      <form className="row gap" onSubmit={onSearchSubmit}>
        <input
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Search movies & shows…"
          className="input"
        />
        {query && (
          <button type="button" className="btn subtle" onClick={clearSearch}>
            Clear
          </button>
        )}
        <button
          type="button"
          className="btn outline"
          onClick={() => setShowFilters((s) => !s)}
          aria-expanded={showFilters}
        >
          {showFilters ? "Hide filters" : "Show filters"}
        </button>
        <button
          type="button"
          className="btn outline"
          onClick={() => setMode((m) => (m === "swipe" ? "grid" : "swipe"))}
        >
          {mode === "swipe" ? "Grid view" : "Swipe view"}
        </button>

        {/* live summary */}
        <div className="row" style={{ alignItems: "center", gap: 8 }}>
          <strong>{rows.length}</strong>
          <span className="muted">results</span>
          {providers && (
            <span className="muted">
              • providers: {providers.replaceAll("|", ", ")}
            </span>
          )}
          {type !== "all" && <span className="muted">• type: {type}</span>}
          {query.trim() && (
            <span className="muted">• search: “{query.trim()}”</span>
          )}
        </div>
      </form>

      {/* Filters panel */}
      {showFilters && (
        <div className="stack gap">
          {/* Type filter */}
          <div className="row gap" role="tablist" aria-label="Type">
            {["all", "movie", "tv"].map((t) => (
              <button
                key={t}
                type="button"
                className={t === type ? "btn primary" : "btn"}
                onClick={() => changeType(t)}
                role="tab"
                aria-selected={t === type}
              >
                {t}
              </button>
            ))}
          </div>

          {/* Provider filter buttons */}
          <div className="row wrap gap">
            {SERVICE_TOKENS.map((t) => {
              const active = selectedTokens.has(t);
              return (
                <button
                  key={t}
                  type="button"
                  onClick={() => toggleProvider(t)}
                  className={active ? "btn primary" : "btn"}
                  title={t}
                  aria-pressed={active}
                >
                  {t}
                </button>
              );
            })}
            {(selectedTokens.size > 0 || type !== "all") && (
              <button
                type="button"
                className="btn subtle"
                onClick={clearFilters}
              >
                Clear filters
              </button>
            )}
          </div>
        </div>
      )}

      {err && <div className="err">{err}</div>}

      {/* SWIPE or GRID */}
      {mode === "swipe" ? (
        <div className="swipe-wrap">
          <h2 className="center" style={{ marginBottom: 12 }}>
            ARE YOU INTERESTED?
          </h2>
          <div className="swipe-card">
            {current ? (
              <>
                <img
                  className="swipe-poster"
                  src={img.poster(current.poster_path, 500)}
                  alt={current.title}
                />
                <div className="swipe-meta">
                  <div className="title">{current.title}</div>
                  {prov.length > 0 && (
                    <div className="muted">
                      {prov
                        .map((p) => p.provider_name || p.name)
                        .filter(Boolean)
                        .join(" • ")}
                    </div>
                  )}
                </div>
              </>
            ) : (
              <div className="center muted" style={{ padding: 40 }}>
                {loading ? "Loading…" : "No more results"}
              </div>
            )}
          </div>
          <div className="swipe-actions">
            <button className="btn danger big" onClick={() => act(false)}>
              &larr; NO
            </button>
            <button className="btn success big" onClick={() => act(true)}>
              YES &rarr;
            </button>
          </div>
        </div>
      ) : (
        <div className="grid movies">
          {rows.map((r) => (
            <div key={`${r.is_tv ? "tv" : "m"}-${r.tmdb_id}`} className="card">
              <img src={img.poster(r.poster_path)} alt={r.title} />
              <div className="title">{r.title}</div>
              <Providers id={r.tmdb_id} isTv={!!r.is_tv} />
            </div>
          ))}
        </div>
      )}

      {/* Load more */}
      <div className="center" style={{ marginTop: 16 }}>
        <button
          className="btn"
          disabled={loading}
          onClick={() => setPage((p) => p + 1)}
        >
          {loading ? "Loading…" : "Load more"}
        </button>
      </div>
    </div>
  );
}
