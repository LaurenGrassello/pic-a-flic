import { useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { api } from "../api";

export default function Login() {
  const [email, setEmail] = useState("dev@example.com");
  const [password, setPassword] = useState("pass1234");
  const [status, setStatus] = useState("");
  const [ok, setOk] = useState(false);
  const navigate = useNavigate();
  const location = useLocation();
  const redirectTo = location.state?.from || "/account";

  async function submit(e) {
    e.preventDefault();
    setStatus("");
    try {
      const { token } = await api.login(email, password);
      setOk(true);
      setStatus(`logged in (token ${token.slice(0, 16)}…)`);
      navigate(redirectTo, { replace: true });
    } catch (e) {
      setOk(false);
      setStatus(e.message || "Login failed");
    }
  }

  return (
    <div className="card" style={{ maxWidth: 420, marginInline: "auto" }}>
      <form onSubmit={submit} className="col gap">
        <input
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder="email"
          type="email"
          autoComplete="email"
          required
        />
        <br></br>
        <br></br>
        <input
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          placeholder="password"
          type="password"
          autoComplete="current-password"
          required
        />
        <br></br>
        <br></br>
        <button type="submit">Login</button>

        {status && (
          <span className={`muted ${ok ? "ok" : "err"}`}>{status}</span>
        )}

        <div className="muted" style={{ marginTop: 8 }}>
          <Link to="/register">Create an Account | </Link>
          <Link to="/forgot">Forgot Password</Link>
        </div>
      </form>
    </div>
  );
}
