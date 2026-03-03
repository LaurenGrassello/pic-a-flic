import { useEffect, useState } from "react";
import { api } from "../api";

export default function ProfileFriends() {
  const [rows, setRows] = useState([]);
  const [err, setErr] = useState("");

  useEffect(() => {
    (async () => {
      try {
        if (!api.friends) throw new Error("Friends API not implemented yet");
        const data = await api.friends(); // GET /profile/friends
        setRows(data);
      } catch (e) {
        setErr(e.message);
      }
    })();
  }, []);

  if (err) {
    return (
      <div className="stack gap">
        <h2>Friends</h2>
        <div className="muted">{err}</div>
        <p className="muted">We’ll hook this up next—backend route pending.</p>
      </div>
    );
  }

  return (
    <div className="stack gap">
      <h2>Friends</h2>
      {rows.length === 0 ? (
        <div className="muted">No friends yet.</div>
      ) : (
        <ul className="stack">
          {rows.map((f) => (
            <li key={f.id} className="card row gap">
              <strong>{f.display_name}</strong>
              <span className="muted">{f.email}</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
