import { Link, useNavigate, NavLink } from "react-router-dom";
import { useAuthState } from "../lib/auth";
import { api } from "../api";

export default function Header() {
  const auth = useAuthState();
  const isAuthed =
    typeof auth === "boolean"
      ? auth
      : !!(auth?.authed ?? auth?.token ?? auth?.user);
  const navigate = useNavigate();

  function logout() {
    api.logout?.();
    navigate("/login", { replace: true });
  }

  return (
    <header className="bar header-flex">
      <h1 className="brand">pic-a-flic</h1>

      {isAuthed && (
        <>
          <NavLink to="/profile">My Profile</NavLink>
          <br></br>
          <br></br>
          <button className="btn subtle logout" onClick={logout}>
            Logout
          </button>
          <br></br>
          <br></br>
          <nav className="nav-links">
            <NavLink to="/account">Account | </NavLink>
            <Link to="/browse">Browse | </Link>
            <Link to="/challenge">Challenge | </Link>
            <Link to="/matches">Matches</Link>
          </nav>
          <br></br>
        </>
      )}
    </header>
  );
}
