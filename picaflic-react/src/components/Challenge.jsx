import { useEffect, useState } from "react";
import { api, img } from "../api";

export default function Challenge() {
  const [row, setRow] = useState(null);
  const [err, setErr] = useState("");
  const [loading, setLoading] = useState(false);

  function fetchOne() {
    setLoading(true);
    setErr("");
    api
      .challenge({ region: "US", limit: 1 }) // backend ignores limit but ok to pass
      .then((list) => {
        setRow(Array.isArray(list) && list.length ? list[0] : null);
        if (Array.isArray(list) && list.length === 0) {
          setErr("No pick available. Try again.");
        }
      })
      .catch((e) => {
        setErr(e.message || "Failed to fetch");
      })
      .finally(() => setLoading(false));
  }

  useEffect(() => {
    fetchOne();
  }, []);

  return (
    <div className="stack gap">
      <h2>Challenge Me</h2>

      {err && <div className="err">{err}</div>}

      {row && (
        <div className="card big">
          <img src={img.poster(row.poster_path, 500)} alt={row.title} />
          <div className="stack">
            <h3>{row.title}</h3>
            <div className="muted">{row.is_tv ? "TV Show" : "Movie"}</div>
          </div>
        </div>
      )}

      <div className="row gap">
        <button className="btn" onClick={fetchOne} disabled={loading}>
          {loading ? "Picking…" : "New challenge"}
        </button>
      </div>
    </div>
  );
}
