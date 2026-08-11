# Deploying for pilot testing — Backend (Render) + Frontend (Netlify)

This is a real, working config (Dockerfile, entrypoint, render.yaml, netlify.toml) — not just advice. It's set up so **your SQLite database and every uploaded file (logos, payment proofs) survive restarts and redeploys**, which is the one thing that actually breaks on Render's free tier if you're not careful (see the note in `render.yaml`).

## Cost: not fully free, and here's why

Render's free web-service tier has an **ephemeral disk** — it wipes clean on every restart or redeploy. For a pilot with real schools uploading real payment proofs, that's a data-loss risk, not a hypothetical. The `render.yaml` here is set to the **Starter plan (~$7/month)** with a small persistent disk attached specifically to avoid that. Worth the $7 for a pilot you're using to actually sell the product.

---

## Backend → Render

1. **Push this repo to GitHub** (if it isn't already).
2. In Render: **New → Blueprint**, point it at your GitHub repo. Render will read `render.yaml` automatically and set up the web service + disk.
3. Before the first deploy finishes successfully, set these env vars in Render's dashboard (marked `sync: false` in `render.yaml`, meaning Render won't auto-fill them — you do it once, manually):
   - `APP_KEY` — generate this **locally**, not on Render: run `php artisan key:generate --show` in your local project and paste the output (starts with `base64:`). Keep it fixed — don't let it regenerate on every boot, or every existing session/password-reset link breaks.
   - `APP_URL` — after the first deploy, Render gives you a `https://school-saas-api-xxxx.onrender.com` URL. Set `APP_URL` to that (or your real custom domain once you have one).
   - `FRONTEND_URL` — your Netlify URL (see below). This locks CORS down to just your frontend once `APP_ENV=production` (see the `config/cors.php` change — `'*'` is automatically excluded in production).
   - `MAIL_*` — your SMTP details, for password resets, fee reminders, announcements. Any transactional email provider works (Brevo/Sendinblue has a genuinely free tier for low volume, worth checking for a pilot).
4. Redeploy after setting those. Check `https://your-app.onrender.com/up` — that's Laravel's built-in health check, should return 200.

**What the Docker setup actually does**, if you want to know: `docker/entrypoint.sh` runs on every container start — creates the SQLite file on the persistent disk if it doesn't exist yet, runs pending migrations, and caches config/routes for performance. This all happens automatically; you don't run anything by hand after the first setup.

## Frontend → Netlify

1. **New site from Git**, point at your `school-saas-web` repo. Netlify reads `netlify.toml` automatically (build command + publish dir + the SPA redirect rule React Router needs — without it, refreshing on any page other than the homepage 404s).
2. Set one env var in Netlify's dashboard: `VITE_API_BASE_URL` = `https://your-render-url.onrender.com/api` (note the `/api` suffix).
3. Deploy. Netlify gives you a `https://your-app.netlify.app` URL immediately, with HTTPS already handled.
4. Go back to Render and set `FRONTEND_URL` to this Netlify URL (step 3 above, if you hadn't yet).

## Before you actually demo this to a school

- **A real domain matters more than the tech.** `xxxx.onrender.com` and `xxxx.netlify.app` read as "unfinished project" to a Nigerian school proprietor. A `.com.ng` domain is cheap (~₦3,000-8,000/year from Whogohost or similar) — point it at Netlify (frontend) via their DNS instructions, and consider a subdomain like `api.yourschool.com.ng` pointed at Render for the backend.
- **Composer version fix from Stage 41 must be installed** — `composer update maatwebsite/excel` needs to run as part of the Docker build (it will, automatically, since `composer install` reads the corrected `composer.json`) — just flagging that this is why the results-export fix depends on this deploy going through cleanly.
- Test the full loop once live: register a school, log in as each role, upload a logo, submit a bank transfer proof, download a results spreadsheet, download a report card PDF. Those are the features most likely to reveal an environment difference between your local XAMPP setup and Render's Linux container (file uploads and PDF/Excel generation are the classic places that differ).

## When you outgrow this

SQLite is fine for a pilot's realistic concurrency (a handful of schools, most activity clustered in school hours). When you're past ~15-20 active schools or see write-lock errors under load, that's your signal to move to managed Postgres/MySQL — not before. Don't do it preemptively; it's a real migration effort you don't need yet.
