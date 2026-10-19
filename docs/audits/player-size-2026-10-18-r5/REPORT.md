# Player size visual audit — round 5 short landscape (18 Oct 2026)

**Host:** local `http://127.0.0.1:8000/play` (Cleaning House)  
**Focus:** Short landscape transport clipping  
**Compared to:** [round 4 report](../player-size-2026-10-18-r4/REPORT.md)

### Changes in this round

1. **Restored healthy control sizes** — buttons **1.9rem** / play **2.15rem**, transport `min-height: 5.5rem`, natural height (no crush).
2. **Wider rail than cover** — `--player-rail: min(max(12rem, card), 50vw)` so all **five** control buttons sit inside the transport; cover stays a smaller centred square.
3. **Title stays one line** (ellipsis) so it cannot wrap onto the control row.
4. Kept r4 wins: one short size, no art clip, username-only strip, wrapping beggars.

### Scorecard

| Check | Result |
|-------|--------|
| Five controls inside transport | **Pass** (repeat / prev / play / next / cast) |
| Play / control hit size | **34.4px / 30.4px** |
| Title vs controls overlap | **None** (gap ~5.6px) |
| Cover square, not clipped | **Pass** |
| Strip → cover gap | **8px** |
| Stack fits viewport | **Pass** |

---

## Short landscape — `r5-short.png`

![Short landscape](r5-short.png)

| Measure | Value |
|---------|------:|
| Rail / transport width | ~192 / ~184 |
| Cover | ~117×117 (square, centred) |
| Transport height | ~102px (`min-height` 88px) |
| Control buttons | 5, all inside transport |
| Title → controls gap | ~5.6px |
| Scene max-height / overflow | none / visible |
| Username | Cato (no “Signed in as”) |

**Fair evaluation:** **Pass.** Media controls match the healthier r3 hit targets again; cover stays a full square (slightly smaller so the rail can host the control row).

## Verdict

Short landscape transport clipping is resolved by **wider rail + restored button sizes**, without bringing back artwork clip. Ready for checkpoint when you want it.
