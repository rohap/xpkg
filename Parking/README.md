## Automatic Parking Lot (Plain PHP)

Run locally:

```bash
php -S 127.0.0.1:8000 -t public
```

Then open `http://127.0.0.1:8000`.

### Features

- Enter vehicle: assigns nearest available spot by type and creates a ticket
- Exit vehicle: computes fee and frees the spot
- Dashboard: stats and list of active tickets

### Configuration

- Data files are JSON in `data/` (`spots.json`, `tickets.json`).
- Initial spots are seeded on first run: 20 cars, 10 bikes, 5 trucks.

