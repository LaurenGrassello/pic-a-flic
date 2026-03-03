import { NavLink } from "react-router-dom";
import "./account.css";

export default function Account() {
  return (
    <div className="account-hero">
      <MenuButton to="/browse" label="BROWSE" />
      <MenuButton to="/watchlist" label="WATCHLIST" />
      <MenuButton to="/challenge" label="RANDOMIZE" />
      <MenuButton to="/quiz" label="TAKE A QUIZ" />
    </div>
  );
}

function MenuButton({ to, label }) {
  return (
    <NavLink className="big-menu-btn" to={to}>
      {label}
    </NavLink>
  );
}
