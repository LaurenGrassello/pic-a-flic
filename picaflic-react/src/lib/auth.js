import { useEffect, useState } from "react";

export const isAuthed = () => !!localStorage.getItem("token");

export function useAuthState() {
  const [authed, setAuthed] = useState(isAuthed());
  useEffect(() => {
    const onChange = () => setAuthed(isAuthed());
    window.addEventListener("paf-auth-changed", onChange);
    return () => window.removeEventListener("paf-auth-changed", onChange);
  }, []);
  return authed;
}
