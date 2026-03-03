import React from "react";
import ReactDOM from "react-dom/client";
import {
  createBrowserRouter,
  RouterProvider,
  Navigate,
} from "react-router-dom";
import App from "./App.jsx";
import Browse from "./components/Browse.jsx";
import Challenge from "./components/Challenge.jsx";
import Matches from "./components/Matches.jsx";
import Login from "./components/Login.jsx";
import Forgot from "./components/Forgot.jsx";
import Account from "./components/Account.jsx";
import Reset from "./components/Reset.jsx";
import Register from "./components/Register.jsx";
import RequireAuth from "./components/RequireAuth.jsx";
import Profile from "./components/Profile.jsx";
import ProfileAccount from "./components/ProfileAccount.jsx";
import ProfileServices from "./components/ProfileServices.jsx";
import ProfileFriends from "./components/ProfileFriends.jsx";
import ProfileLikes from "./components/ProfileLikes.jsx";
import ProfileWatchlist from "./components/ProfileWatchlist.jsx";

import "./index.css";

const Watchlist = () => (
  <div className="container">Watchlist (coming soon)</div>
);
const Quiz = () => <div className="container">Quiz (coming soon)</div>;

const router = createBrowserRouter([
  {
    element: <App />,
    children: [
      { path: "/", element: <Navigate to="/account" replace /> }, // 👈 land here
      { path: "/login", element: <Login /> },
      { path: "/register", element: <Register /> },
      { path: "/forgot", element: <Forgot /> },
      { path: "/reset", element: <Reset /> },
      {
        element: <RequireAuth />,
        children: [
          { path: "/account", element: <Account /> }, // 👈 new
          { path: "/browse", element: <Browse /> },
          { path: "/challenge", element: <Challenge /> },
          { path: "/matches", element: <Matches /> },
          { path: "/watchlist", element: <Watchlist /> }, // placeholder
          { path: "/quiz", element: <Quiz /> }, // placeholder
        ],
      },
    ],
  },
]);

ReactDOM.createRoot(document.getElementById("root")).render(
  <RouterProvider router={router} />
);
