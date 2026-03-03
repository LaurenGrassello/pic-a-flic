import { useState } from "react";
import { api } from "../api";

export default function Forgot() {
  const [email, setEmail] = useState("");
  const [status, setStatus] = useState("");

  async function submit(e) {
    e.preventDefault();
    setStatus("");
    try {
      await api.forgot(email);
      setStatus("If the email exists, we sent a reset link.");
    } catch (e) {
      setStatus("If the email exists, we sent a reset link.");
    }
  }

  return (
    <div className="card stack gap">
      <h2>Forgot password</h2>
      <form onSubmit={submit} className="row gap">
        <input
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder="you@example.com"
        />
        <button className="btn" type="submit">
          Send reset link
        </button>
      </form>
      {status && <div className="muted">{status}</div>}
    </div>
  );
}
