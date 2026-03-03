import { useMemo, useState } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import { api } from "../api";

function useTokenFromQuery() {
  const { search } = useLocation();
  return useMemo(
    () => new URLSearchParams(search).get("token") || "",
    [search]
  );
}

export default function Reset() {
  const token = useTokenFromQuery();
  const nav = useNavigate();
  const [p1, setP1] = useState("");
  const [p2, setP2] = useState("");
  const [msg, setMsg] = useState("");

  async function submit(e) {
    e.preventDefault();
    setMsg("");
    if (p1.length < 6 || p1 !== p2) {
      setMsg("Passwords must match and be at least 6 characters.");
      return;
    }
    try {
      await api.resetPassword(token, p1);
      setMsg("Password updated. Redirecting to login…");
      setTimeout(() => nav("/login"), 1000);
    } catch (e) {
      setMsg(e.message || "Reset failed");
    }
  }

  return (
    <div className="card stack gap">
      <h2>Reset password</h2>
      <form onSubmit={submit} className="stack gap">
        <input
          type="password"
          value={p1}
          onChange={(e) => setP1(e.target.value)}
          placeholder="New password"
        />
        <input
          type="password"
          value={p2}
          onChange={(e) => setP2(e.target.value)}
          placeholder="Confirm password"
        />
        <button className="btn" type="submit" disabled={!token}>
          Reset
        </button>
      </form>
      {msg && <div className="muted">{msg}</div>}
    </div>
  );
}
