# DayPilot Lite for JavaScript

Embedded component for the Workshop module (Dolibarr).

## Source

- **Package:** `@daypilot/daypilot-lite-javascript`
- **Version:** 5.5.0 (build 2026.2.813-lite)
- **Homepage:** https://www.daypilot.org/
- **npm:** https://www.npmjs.com/package/@daypilot/daypilot-lite-javascript
- **License:** Apache License 2.0

## Files

| File | Description |
|------|-------------|
| `daypilot-javascript.min.js` | Minified library (Calendar, Scheduler, Navigator) |
| `LICENSE.txt` | Apache License 2.0 full text |
| `NOTICE.txt` | Attribution notice |

## Usage in Workshop

Used for the **mechanics daily planning** view (`workshop_planning.php`, mode "journee").
Provides a resource calendar with one column per mechanic and drag & drop
support for scheduling jobs.

```php
// Include in page header via llxHeader()
$arrayofjs = array('/workshop/includes/daypilot/daypilot-javascript.min.js');
```

```javascript
// Initialize resource calendar
const dp = new DayPilot.Calendar("element-id", {
    viewType: "Resources",
    columns: [
        { name: "Mechanic Name", id: "user_id" }
    ]
});
dp.init();
```

## How to update

```bash
cd /tmp
npm pack @daypilot/daypilot-lite-javascript
tar -xzf daypilot-daypilot-lite-javascript-*.tgz
cp package/daypilot-javascript.min.js /path/to/workshop/includes/daypilot/
cp package/LICENSE.txt /path/to/workshop/includes/daypilot/
cp package/NOTICE.txt /path/to/workshop/includes/daypilot/
```
