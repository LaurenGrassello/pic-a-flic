import { useEffect, useState } from "react";
import { api } from "../api";

export default function ProfileAccount() {
  const [me, setMe] = useState(null);
  const [status, setStatus] = useState("");
  const [ok, setOk] = useState(false);

  useEffect(() => {
    api
      .me()
      .then(setMe)
      .catch((e) => setStatus(e.message || "Failed to load"));
  }, []);

  async function sendReset() {
    try {
      setStatus("Sending reset link…");
      setOk(false);
      await api.forgot(me.email);
      setOk(true);
      setStatus("Reset link sent! Check your email.");
    } catch (e) {
      setOk(false);
      setStatus(e.message || "Failed to send email");
    }
  }

  return (
    <div className="stack gap">
      <h2>Account</h2>
      {!me ? (
        <div className="muted">{status || "Loading…"}</div>
      ) : (
        <div className="card stack">
          <div>
            <strong>Display name:</strong> {me.display_name}
          </div>
          <div>
            <strong>Email:</strong> {me.email}
          </div>

          <div className="row gap">
            <button className="btn" onClick={sendReset}>
              Send password reset email
            </button>
            {status && (
              <span className={`muted ${ok ? "ok" : "err"}`}>{status}</span>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
