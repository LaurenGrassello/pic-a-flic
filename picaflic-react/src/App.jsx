import { Outlet } from "react-router-dom";
import Header from "./components/Header.jsx";
import "./App.css";

export default function App() {
  return (
    <div className="container">
      <Header />
      <main>
        <Outlet />
      </main>
    </div>
  );
}
