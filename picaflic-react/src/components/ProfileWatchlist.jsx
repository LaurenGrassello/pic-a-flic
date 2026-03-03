import { useEffect, useState } from "react";
import { api, img } from "../api";

export default function ProfileWatchlist() {
  const [rows, setRows] = useState([]);
  const [err, setErr] = useState("");

  useEffect(() => {
    (async () => {
      try {
        if (!api.watchlist)
          throw new Error("Watchlist API not implemented yet");
        const data = await api.watchlist(); // GET /profile/watchlist
        setRows(data);
      } catch (e) {
        setErr(e.message);
      }
    })();
  }, []);

  return (
    <div className="stack gap">
      <h2>Watchlist</h2>
      {err && <div className="muted">{err} • backend route pending</div>}

      <div className="grid movies">
        {rows.map((r) => (
          <div key={`${r.is_tv ? "tv" : "m"}-${r.tmdb_id}`} className="card">
            <img src={img.poster(r.poster_path)} alt={r.title} />
            <div className="title">{r.title}</div>
          </div>
        ))}
      </div>
    </div>
  );
}
