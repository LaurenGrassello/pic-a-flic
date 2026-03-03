import { useEffect, useState } from "react";
import { api } from "../api";

const TOKENS = [
  "netflix",
  "disney",
  "hulu",
  "prime",
  "max",
  "appletv",
  "peacock",
];

export default function ProfileServices() {
  const [selected, setSelected] = useState(new Set());
  const [status, setStatus] = useState("");

  // load from localStorage on mount
  useEffect(() => {
    const raw = localStorage.getItem("preferred_services");
    if (raw) setSelected(new Set(JSON.parse(raw)));
  }, []);

  function toggle(t) {
    setSelected((prev) => {
      const next = new Set(prev);
      next.has(t) ? next.delete(t) : next.add(t);
      return next;
    });
  }

  async function save() {
    const arr = Array.from(selected);
    localStorage.setItem("preferred_services", JSON.stringify(arr));
    setStatus("Saved locally.");
    // Optional: if you add a backend route later:
    try {
      if (api.updateServices) {
        await api.updateServices(arr); // POST /profile/services { services: [...] }
        setStatus("Saved!");
      }
    } catch (e) {
      setStatus(`Saved locally. Server update failed: ${e.message}`);
    }
  }

  return (
    <div className="stack gap">
      <h2>Streaming services</h2>

      <div className="row wrap gap">
        {TOKENS.map((t) => {
          const active = selected.has(t);
          return (
            <button
              key={t}
              type="button"
              className={active ? "btn primary" : "btn"}
              onClick={() => toggle(t)}
              aria-pressed={active}
            >
              {t}
            </button>
          );
        })}
        {selected.size > 0 && (
          <button className="btn subtle" onClick={() => setSelected(new Set())}>
            Clear
          </button>
        )}
      </div>

      <div className="row gap">
        <button className="btn" onClick={save}>
          Save
        </button>
        {status && <span className="muted">{status}</span>}
      </div>

      <p className="muted">
        Tip: Browse will auto-apply these (client-side) until we wire a server
        preference.
      </p>
    </div>
  );
}
