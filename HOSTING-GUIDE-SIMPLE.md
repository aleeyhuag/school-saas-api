# Putting your school app online — a complete beginner's guide

This assumes you know nothing about hosting. Every step tells you exactly what to click and what to type. Read it once all the way through before starting, then come back and do it step by step.

**You do NOT need to rebuild anything.** All the coding work (Stages 1–44) is already done. "Hosting" just means putting the finished code on a computer that's always on and connected to the internet, so schools can reach it from their phones and computers instead of only working on your own PC. Nothing in this guide involves writing or changing code.

---

## The three pieces, explained simply

Your project is actually two separate things that need two separate homes:

1. **The frontend** — what people actually see and click (the login screen, the dashboards, the buttons). This lives in your `school-saas-web` folder.
2. **The backend** — the "brain" that stores data and does the work behind the scenes (checking passwords, saving student records, computing results). This lives in your `school-saas-api` folder.
3. **The database** — where all the actual information is stored (every student, every score, every fee payment). We'll set this up as part of the backend.

We're using two services:
- **Netlify** — hosts the frontend. Free.
- **Render** — hosts the backend AND the database together. About **$7-13/month total** — not free, and I'll explain why below.

---

## Why not free, and why not SmartWeb's VPS?

You asked about SmartWeb — I looked it up. Their VPS plans are real and reasonably priced for Nigeria (around ₦10,000-₦48,000/month depending on the plan), but a "VPS" is a bare, empty computer with nothing on it — no web server, no PHP, no database, no security setup, nothing. You'd have to install and configure all of that yourself, by typing commands into a black screen, and keep it updated and secure forever. That's a real, ongoing technical job — the kind an experienced developer would charge you to do and maintain.

Render and Netlify are different: you connect your code once, and they handle installing everything, keeping it running, adding the padlock/HTTPS security icon, and restarting it automatically if anything crashes — with no typing-into-a-black-screen required, ever. Given you told me to assume zero technical knowledge, this is the only realistic choice right now. SmartWeb (or any VPS) becomes worth revisiting later, once you can hire or bring on someone technical full-time to manage a server — not before.

On cost: Render does have a free option, but it deletes your entire database automatically after 30 days with real school data in it, and it "falls asleep" after 15 minutes of no visitors (meaning the first person to open the app after that has to wait 30-50 seconds — bad during a sales demo). For real schools' real data, that's not a safe trade to make just to save $7/month.

---

## About the database — the "do we need to migrate" question

Short answer: **yes, and I've already set it up for you below.** Your app has been using something called SQLite — basically one single file holding all the data, which is fine for testing on your own computer but starts to strain once several people are using the app at the exact same moment (e.g., five different schools' teachers all marking attendance in the same minute). With 20 schools expected in the first quarter, that's a real possibility, not a hypothetical.

I checked your actual code for anything that might break when switching — nothing does; it's a clean switch. I've configured your project to use **PostgreSQL** instead (a proper, industry-standard database built to handle many people at once). The good part: Render provides this automatically as part of the same setup below — you don't sign up anywhere else, don't manage a second account, and don't type a single database command yourself. It's one extra click in the same dashboard.

(You may see MySQL suggested elsewhere as an alternative — it would also work fine, but it would mean signing up for a *third*, completely separate website just for the database. Postgres through Render keeps everything in one place, which matters a lot when you're doing this for the first time.)

---

# Part 1 — Get your code onto GitHub

Both Render and Netlify work by connecting to a place called **GitHub** — think of it as a cloud storage locker specifically for code, that hosting services know how to read from directly. If your code isn't there yet, this is step one, and everything else depends on it.

1. Go to **github.com** and click **Sign up**. Use an email you check regularly. Pick a username (it can be anything, e.g. `agkomputech`).
2. Once signed in, download **GitHub Desktop** from **desktop.github.com** — this gives you a simple point-and-click window instead of typing commands. Install it, then open it and sign in with the GitHub account you just made.
3. In GitHub Desktop, click **File → New Repository**. For "Name," type `school-saas-api`. For "Local Path," click **Choose...** and pick the folder that already contains your `school-saas-api` project on your computer (in XAMPP, that's inside `C:\xampp\htdocs\SaaS\`). Click **Create Repository**.
4. You'll see a list of all your project's files on the left with checkmarks. At the bottom left, type a short message like "Initial upload" in the box, then click the blue **Commit to main** button.
5. Click **Publish repository** at the top. Untick "Keep this code private" only if you're comfortable with that — for now, tick **Keep this code private** (checked) is the safer default. Click **Publish Repository**.
6. Repeat steps 3-5 for your `school-saas-web` folder too, so you end up with **two** separate repositories on GitHub — one for the backend, one for the frontend.

You now have both projects safely on GitHub. Any time you make changes later (like the Stage 41-44 files I've given you), you'll drag the new files into the same folder, then repeat step 4 (type a message, click **Commit to main**, then click **Push origin** at the top) to update GitHub.

**Right now:** copy every file from the `stage41.zip`, `stage42.zip`, `stage43.zip`, and `stage44.zip` folders I've given you into your actual project folders on your computer (matching the paths shown in each README), overwriting the old versions. Then do the commit-and-push steps above so GitHub has the latest code before you continue.

---

# Part 2 — Host the backend on Render

1. Go to **render.com** and click **Get Started**. Sign up using **"Sign up with GitHub"** — this links the two automatically, which saves a step later.
2. Once you're in the Render dashboard, click the **New +** button (top right), then choose **Blueprint**.
3. Render will ask to connect to GitHub — click **Connect account** if it asks, and approve it.
4. You'll see a list of your GitHub repositories — find and click **school-saas-api**.
5. Render will automatically find a file in your project called `render.yaml` (I've already created this for you) and show you what it's about to set up: one web service and one database. Click **Apply** or **Deploy Blueprint**.
6. It'll start building — this takes a few minutes the first time. You'll see logs scrolling by; that's normal, just wait.
7. Once it says your service is **Live**, click on the service name (`school-saas-api`) to open it. Copy the URL shown at the top — it looks like `https://school-saas-api-xxxx.onrender.com`. **Save this URL somewhere**, you'll need it in Part 3.

### A few settings you must fill in by hand

Still on that same service page, click the **Environment** tab on the left. You'll see a list of settings (called "environment variables") — most are already filled in, but a few need your input:

- **APP_KEY**: this is a secret security code your app needs. To generate one: on your own computer, open the Command Prompt (search "cmd" in your Windows start menu), type `cd C:\xampp\htdocs\SaaS\school-saas-api` and press Enter, then type `php artisan key:generate --show` and press Enter. It'll print something starting with `base64:` — copy that entire line, and paste it as the value for `APP_KEY` in Render.
- **APP_URL**: paste the `https://school-saas-api-xxxx.onrender.com` URL you copied in step 7 above.
- **MAIL_MAILER**, **MAIL_HOST**, **MAIL_PORT**, **MAIL_USERNAME**, **MAIL_PASSWORD**, **MAIL_FROM_ADDRESS**: these are for sending emails (password resets, fee reminders). You need an email-sending service — **Brevo** (brevo.com) has a genuinely free plan for the volume you'll need at pilot stage. Sign up there, and it'll give you the exact values to paste into these five fields (their dashboard has a page called "SMTP & API" that shows all five).
- **FRONTEND_URL**: leave this blank for now — you'll fill it in at the very end of Part 3.

After filling each one in, click **Save Changes** at the bottom — Render will automatically restart your app with the new settings.

---

# Part 3 — Host the frontend on Netlify

1. Go to **netlify.com** and click **Sign up**, again choosing **"Sign up with GitHub"**.
2. Click **Add new site → Import an existing project**.
3. Choose **GitHub**, approve the connection if asked, then find and click **school-saas-web**.
4. Netlify will detect the build settings automatically (from a file called `netlify.toml` already in your project). You shouldn't need to change anything here — just click **Deploy**.
5. Before it finishes, click **Site configuration → Environment variables** on the left, then **Add a variable**. For the name, type `VITE_API_BASE_URL`. For the value, paste your Render URL from Part 2 **with `/api` added to the end** — for example `https://school-saas-api-xxxx.onrender.com/api`. Click **Create variable**.
6. Go back to **Deploys** on the left and click **Trigger deploy → Deploy site** so it picks up the setting you just added.
7. Once it finishes (a minute or two), you'll see a URL like `https://something-random.netlify.app` — this is your live website. Open it and check the login page loads.

### Connect the two together

Go back to **Render**, open your backend service, go to **Environment** again, and now fill in **FRONTEND_URL** with the `https://something-random.netlify.app` address from step 7 above. Click **Save Changes**.

---

# Part 4 — Test that everything actually works

Open your Netlify URL in a browser and try, in order:
1. Register a brand new test school.
2. Log in as that school's Proprietor.
3. Upload a school logo — confirm it actually shows up (this was broken before Stage 42's fix; if it's still broken, the fix wasn't installed correctly).
4. Try downloading a results spreadsheet.
5. Log out, log back in.

If any step fails, note the exact page and what happened — that's the specific thing to troubleshoot, rather than starting over.

---

# Part 5 — Get a real domain (do this once the above is working)

`xxxx.onrender.com` and `xxxx.netlify.app` look unfinished to a school proprietor deciding whether to trust you with their students' data. A real domain fixes that.

1. Buy a `.com.ng` domain from **Whogohost** (whogohost.com) — a few thousand naira per year.
2. In Netlify: **Domain management → Add a domain**, type your domain, follow the on-screen instructions — it'll show you 2-4 lines to copy into Whogohost's settings (called "DNS records"). Whogohost has a page in your account for this, usually called "Manage DNS" or "Nameservers."
3. Takes up to 24-48 hours to fully work, often much faster.
4. Once your domain is live pointing at Netlify, go back to Render and update `APP_URL` and to Netlify's env var and Render's `FRONTEND_URL` to use the new domain instead of the `.onrender.com`/`.netlify.app` ones.

---

## What I already changed in your project for this

- `render.yaml` — tells Render exactly what to build (backend + database) automatically, so you don't configure it by hand.
- `Dockerfile` — the recipe Render uses to package your backend correctly, already written.
- `netlify.toml` — tells Netlify how to build your frontend, already written.
- Your database is now configured for PostgreSQL instead of SQLite, ready for real growth to 20+ schools.

You don't need to open or understand any of these files — just make sure they're included when you push your code to GitHub in Part 1.
