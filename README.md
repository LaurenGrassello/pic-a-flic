# pic-a-flic React demo

A minimal React (Vite) frontend that talks to your LAMP API at `VITE_API_BASE` (default `http://localhost:8080`).

## Quick start

```bash
# 1) set API base (optional)
cp .env.example .env
# edit .env if your API isn't on http://localhost:8080

# 2) install deps
npm install

# 3) run dev server (http://localhost:5173)
npm run dev
```

Make sure your API CORS allowlist includes `http://localhost:5173`.

In the UI:
- Login with `dev@example.com` / `pass1234`
- Load **Deck** (unswiped) or **For You**
- Swipe 👍/👎, then check **Matches** with your friend's user id.
