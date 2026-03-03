import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { api } from "../api";

export default function Register() {
  const [displayName, setDisplayName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [status, setStatus] = useState("");
  const [ok, setOk] = useState(false);
  const navigate = useNavigate();

  async function submit(e) {
    e.preventDefault();
    setStatus("");
    try {
      await api.register(email, password, displayName);
      await api.login(email, password); // auto-sign-in
      setOk(true);
      setStatus("Account created!");
      navigate("/browse", { replace: true });
    } catch (e) {
      setOk(false);
      setStatus(e.message || "Registration failed");
    }
  }

  return (
    <div className="card" style={{ maxWidth: 420, marginInline: "auto" }}>
      <h2>Create an account</h2>
      <form onSubmit={submit} className="col gap">
        <input
          value={displayName}
          onChange={(e) => setDisplayName(e.target.value)}
          placeholder="display name"
          required
        />
        <input
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder="email"
          type="email"
          autoComplete="email"
          required
        />
        <input
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          placeholder="password (min 6)"
          type="password"
          minLength={6}
          autoComplete="new-password"
          required
        />
        <button type="submit">Create account</button>

        {status && (
          <span className={`muted ${ok ? "ok" : "err"}`}>{status}</span>
        )}

        <div className="muted" style={{ marginTop: 8 }}>
          Already have an account? <Link to="/login">Log in</Link>
        </div>
      </form>
    </div>
  );
}
