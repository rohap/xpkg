<?php
declare(strict_types=1);

// Simple front controller for the Automatic Parking Lot (no framework)

// Autoload classes from src/
require_once __DIR__ . '/../src/autoload.php';

use App\Service\ParkingService;

$service = new ParkingService(
    __DIR__ . '/../data/spots.json',
    __DIR__ . '/../data/tickets.json'
);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'enter') {
        $plate = trim((string) ($_POST['plate'] ?? ''));
        $type = (string) ($_POST['type'] ?? 'car');
        try {
            $ticketId = $service->enterVehicle($plate, $type);
            header('Location: /?entered=1&ticket=' . urlencode($ticketId));
            exit;
        } catch (Throwable $e) {
            header('Location: /?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
    if ($action === 'exit') {
        $ticketId = trim((string) ($_POST['ticket_id'] ?? ''));
        try {
            $fee = $service->exitVehicle($ticketId);
            header('Location: /?exited=1&fee=' . urlencode(number_format($fee, 2)));
            exit;
        } catch (Throwable $e) {
            header('Location: /?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
}

$stats = $service->getDashboardStats();
$activeTickets = $service->listActiveTickets();

function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Automatic Parking Lot</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            color: #111;
        }

        h1 {
            margin-bottom: 8px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
        }

        .card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 16px;
        }

        .badge {
            display: inline-block;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
        }

        input,
        select,
        button {
            padding: 8px;
            font-size: 14px;
        }

        label {
            display: block;
            font-size: 12px;
            margin-bottom: 4px;
            color: #374151;
        }

        .row {
            display: flex;
            gap: 8px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            border-bottom: 1px solid #eee;
            padding: 8px;
            text-align: left;
        }

        .alert {
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 12px;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
    </style>
</head>

<body>
    <h1>Automatic Parking Lot</h1>
    <p class="badge">Simple PHP, no framework</p>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error"><?php echo h((string) $_GET['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['entered'])): ?>
        <div class="alert alert-success">Vehicle entered. Ticket ID:
            <strong><?php echo h((string) ($_GET['ticket'] ?? '')); ?></strong></div>
    <?php endif; ?>
    <?php if (isset($_GET['exited'])): ?>
        <div class="alert alert-success">Vehicle exited. Fee:
            <strong>$<?php echo h((string) ($_GET['fee'] ?? '0.00')); ?></strong></div>
    <?php endif; ?>

    <div class="grid" style="margin-top:16px;">
        <div class="card">
            <h3>Stats</h3>
            <ul>
                <li>Total spots: <strong><?php echo (int) $stats['total_spots']; ?></strong></li>
                <li>Available: <strong><?php echo (int) $stats['available_spots']; ?></strong></li>
                <li>Occupied: <strong><?php echo (int) $stats['occupied_spots']; ?></strong></li>
                <li>Active tickets: <strong><?php echo (int) $stats['active_tickets']; ?></strong></li>
            </ul>
        </div>
        <div class="card">
            <h3>Enter Vehicle</h3>
            <form method="post">
                <input type="hidden" name="action" value="enter" />
                <label>Plate</label>
                <input name="plate" placeholder="ABC-1234" required />
                <label style="margin-top:8px;">Type</label>
                <select name="type">
                    <option value="car">Car</option>
                    <option value="bike">Bike</option>
                    <option value="truck">Truck</option>
                </select>
                <div style="margin-top:12px;">
                    <button type="submit">Assign Spot & Create Ticket</button>
                </div>
            </form>
        </div>
        <div class="card">
            <h3>Exit Vehicle</h3>
            <form method="post">
                <input type="hidden" name="action" value="exit" />
                <label>Ticket ID</label>
                <input name="ticket_id" placeholder="e.g. TCK-0001" required />
                <div style="margin-top:12px;">
                    <button type="submit">Compute Fee & Release Spot</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <h3>Active Tickets</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Plate</th>
                    <th>Type</th>
                    <th>Spot</th>
                    <th>Entered At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activeTickets as $t): ?>
                    <tr>
                        <td><?php echo h($t['id']); ?></td>
                        <td><?php echo h($t['plate']); ?></td>
                        <td><?php echo h($t['type']); ?></td>
                        <td><?php echo h((string) $t['spot_id']); ?></td>
                        <td><?php echo h(date('Y-m-d H:i:s', (int) $t['entry_ts'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>

</html>

