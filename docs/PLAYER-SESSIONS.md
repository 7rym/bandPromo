# Player sessions (tester note)

bandPromo uses **two different “sessions”**. They sound alike; they do different jobs.

## 1. Login session (staying signed in)

After you log in, PHP keeps a login cookie for that browser.

**Default:** Settings → Basics → **Stay signed in: On**. Operators can turn it Off to restore the older idle timeout.

### Stay signed in On (default)

| Behaviour | Detail |
| --- | --- |
| How long | About **one year**, renewed while a player or admin tab is open (quiet check about every **5 minutes**, and when you bring the tab back). Playback activity renews it too. |
| Closing the browser | You stay signed in on that browser until the cookie ages out or you use **Logout**. |
| When it ends | **Logout**, or the cookie finally expires. You should not be kicked for ordinary idle time. |
| What it is not | It does **not** stop the music after 15 minutes. It only decides whether you are still signed in. |

### Stay signed in Off (auto sign-out)

| Behaviour | Detail |
| --- | --- |
| How long idle | About **24 minutes** with no successful session refresh (host PHP default `session.gc_maxlifetime` = 1440 seconds). Hosts can change that. |
| While a tab is open | The same 5‑minute check renews the login. |
| Closing the browser | Ends the cookie (browser session cookie). |
| When it ends | Login page with **Your session expired. Please log in again.** |

## 2. Listening / analytics session (stats only)

Separately, the player groups listening into analytics “sessions” for reports. **Unaffected** by Stay signed in.

| Behaviour | Detail |
| --- | --- |
| Starts | When playback actually begins and no analytics session is active (`play_start`). |
| Ends after idle | If **no music is playing for 15 minutes**, the analytics session ends (`session_end` with reason inactivity). |
| Does it log you out? | **No.** You stay signed in. Pressing play again starts a **new** analytics session. |
| Also ends | When you leave the page (tab close / navigate away). |

## Quick answers for testers

- **“I got kicked to login”** → with Stay signed in On that should be rare (Logout, cleared cookies, or a very old cookie). With Off, the host idle window or a closed browser ends the login.
- **“Did my 15‑minute pause log me out?”** → No. That only closes the listening stats window.
- **“Where do I change this?”** → Settings → Basics → **Stay signed in:** On|Off. Listening idle stays fixed at 15 minutes.
