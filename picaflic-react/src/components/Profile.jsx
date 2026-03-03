import { Link } from "react-router-dom";
import { useEffect, useState } from "react";
import { api } from "../api";

export default function Profile() {
  const [me, setMe] = useState(null);
  const [err, setErr] = useState("");

  useEffect(() => {
    api
      .me()
      .then(setMe)
      .catch((e) => setErr(e.message || "Failed to load"));
  }, []);

  return (
    <div className="stack gap">
      <h2>My Profile</h2>
      {err && <div className="err">{err}</div>}
      {me && (
        <div className="card">
          <div>
            <p>
              Display Name: <strong>{me.display_name}</strong>
            </p>
          </div>
          <p>
            Email: <strong>{me.email}</strong>
          </p>
        </div>
      )}

      <nav className="stack gap">
        <Link className="btn" to="/profile/account">
          Account |
        </Link>
        <Link className="btn" to="/profile/services">
          Streaming services |
        </Link>
        <Link className="btn" to="/profile/friends">
          Friends |
        </Link>
        <Link className="btn" to="/profile/likes">
          Likes |
        </Link>
        <Link className="btn" to="/profile/watchlist">
          Watchlist
        </Link>
      </nav>
    </div>
  );
}
