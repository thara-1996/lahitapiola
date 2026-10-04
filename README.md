# TurvaTarkistus (Safety Check)

A mobile-first prototype that helps customers find the risks in their everyday life and take simple actions to prevent losses before they happen.

Built for the **LocalTapiola Ostrobothnia challenge "Identify your potential risks"** at the VES Hackathon 2026.

> The best loss is the one that never occurs.

---

## The challenge

LocalTapiola wants to be more than a claims payer. The brief asks for a solution that:

- lets customers identify potential risks in different parts of their lives,
- gives them suggested actions to prevent losses,
- motivates them to adopt it and keep using it,
- creates value for LocalTapiola as well as for the customer.

## Our solution

TurvaTarkistus turns risk prevention into a short, rewarding routine.

1. **Quick check** (about 1 minute) asks 8 simple questions about the home.
2. **Risk dashboard** shows an overall score plus fire, water, security, property and claims risk.
3. **Alerts** explain what could happen, what to do and which insurance is affected.
4. **Points and levels** reward every completed action.
5. **Notifications** remind customers about overdue checks, seasonal risks and stale assessments.
6. **Chatbot** answers questions using the customer's own data.

### Win-win

| For the customer | For LocalTapiola |
|---|---|
| Fewer accidents and damages | Fewer and smaller claims |
| Simple, concrete actions | A regular, positive customer touchpoint |
| Points, levels and rewards | Anonymised regional risk insight |
| A feeling of control | Higher loyalty and engagement |
| Clear reminders at the right time | A natural moment to review coverage |

---

## Features

- **Splash screen and prototype login** (one-tap login, no credentials)
- **Profile** with customer details, insurances, open risks per policy, points and level
- **Risk dashboard** with overall score, High / Medium / Low counts, category bars and assessment history
- **Alerts** generated from rules, sorted by severity, each with "What could happen", a recommended action and a "Mark as done" button
- **Quick check plus optional details** to keep effort low and let customers sharpen the result later
- **Point system** with 4 levels (Bronze, Silver, Gold, Platinum) and example rewards
- **Notification centre** with bell, unread badge, snooze, and optional browser push
- **Rule-based chatbot** that answers from live customer data
- **Guide page** that opens on first visit, with the win-win case and next steps
- **Saved data**: answers, history and points persist between visits

## Tech stack

- PHP 7.4 or newer (single file, no framework, no database)
- Vanilla JavaScript, HTML and CSS
- JSON file storage in `data/`
- Inline SVG line icons (no external libraries or network calls)

## Project structure

```
/turvatarkistus
  ├── index.php     the whole app (splash, login, dashboard, API, chatbot)
  ├── logo.JPG      LocalTapiola logo used on the splash and header
  ├── README.md
  └── data/         created automatically; one JSON file per customer
```

## Run it

1. Put `index.php` and `logo.JPG` in one folder.
2. Make sure the folder is writable so PHP can create `data/`.
3. Start PHP's built-in server in that folder:

```bash
php -S localhost:8000
```

4. Open `http://localhost:8000`.

To test on a phone on the same network, run `php -S 0.0.0.0:8000` and open `http://<your-computer-ip>:8000`.

**Reset the demo:** use "Reset demo data" at the bottom of the Profile tab, or delete `data/demo.json`.

---

## How it works

### Questionnaire
Questions are defined as data in the `Q` list. The `QUICK` list marks which questions appear in the 1-minute quick check. The rest sit in an optional "Add more details" section. To add a question, add one line to `Q`.

### Risk scoring
`score()` combines five categories into an overall percentage:

| Category | Weight |
|---|---|
| Fire | 20% |
| Water | 25% |
| Security | 15% |
| Property / occupancy | 20% |
| Claims / incidents | 20% |

Levels: Low (under 25%), Medium (25 to 49%), High (50 to 74%), Critical (75% or more).

### Alert rules
`RULES` holds one entry per risk: the condition, severity, explanation, recommended action, linked insurance and the answer changes applied when the customer marks it done. Rules are data, so risk experts can maintain them without touching the interface. Rules for traffic, company or farm customers follow the same shape.

### Points

| Action | Points |
|---|---|
| Complete a high-risk action | +50 |
| Complete a medium-risk action | +20 |
| Update the assessment (once per day) | +10 |

Each action earns points only once. Levels: Bronze (0), Silver (100), Gold (250), Platinum (500). Rewards are examples only and need to be defined with the insurer.

### Notifications
The bell shows three kinds of notification:

- **Risk-based:** generated from the alert rules (for example an overdue chimney inspection)
- **Seasonal:** a winter nudge from October to March (tyres, elk, frost)
- **Stale assessment:** a prompt after 90 days

Customers can open, snooze for 7 days, or mark all as read. "Enable push" and "Send test push" use the browser Notification API for demos.

### API
The same file serves a small JSON API for the signed-in customer:

- `index.php?api=load` returns saved answers, history and points
- `index.php?api=save` (POST) stores answers, scores and points

---

## Demo script (for the pitch)

1. Open the app. The guide shows the idea and the win-win case.
2. Tap the bell and show the high-risk notification about the overdue chimney inspection.
3. Open the Risk tab and show the score and categories.
4. Go to Alerts, read "What could happen", then tap **Mark as done** and watch the score drop and points rise.
5. Open the chatbot and ask "What should I fix first?".
6. Show the Profile with level, rewards and linked insurances.

---

## Limitations (it is a prototype)

- Login is a one-tap demo. There is no real authentication.
- Scoring and points run in the browser and are not tamper-proof.
- Customer, insurance and policy data are sample data.
- Only home risks are fully built. Traffic is shown as "coming soon".
- The chatbot is rule-based, not a language model.
- Rewards are examples, not real offers.
- Push notifications only work while the page is open.

## Recommended next steps

1. **Real identification** through a bank identification broker, with per-customer IDs and CSRF protection.
2. **Server-side scoring and points**, so results are auditable and cannot be tampered with.
3. **Connect policy data** (insurances, cover amounts) from LocalTapiola systems.
4. **Real notifications** (web push, SMS or email) from a scheduled job.
5. **Expert-validated rules** built from real claims statistics.
6. **Extend to more customer groups:** traffic for private customers, and assets, traffic and staff wellbeing for company and agricultural customers.
7. **Evidence uploads** (for example a chimney sweep certificate) to close alerts.
8. **Privacy and consent:** clear data use, GDPR handling and retention. Keep the model separate from underwriting and pricing.
9. **Replace the rule-based chatbot** with a server-side language model limited to the customer's own data.
10. **Pilot** in one region and season, then measure the effect on claims.

## Disclaimer

This is a hackathon prototype. Risk scores come from a simplified demo model and are **not** an insurance decision, price, eligibility or underwriting result. Rewards shown are examples only. The LocalTapiola name and logo belong to their owner and are used here only for the hackathon challenge.
