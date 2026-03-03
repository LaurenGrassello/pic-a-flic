import { Navigate, Outlet, useLocation } from "react-router-dom";

export default function RequireAuth() {
  const loc = useLocation();
  const ok = !!localStorage.getItem("token");
  if (!ok)
    return <Navigate to="/login" replace state={{ from: loc.pathname }} />;
  return <Outlet />;
}
