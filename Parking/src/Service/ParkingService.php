<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Spot;
use App\Model\Ticket;
use App\Storage\JsonStore;

final class ParkingService
{
    private JsonStore $spotsStore;
    private JsonStore $ticketsStore;

    public function __construct(string $spotsPath, string $ticketsPath)
    {
        $this->spotsStore = new JsonStore($spotsPath);
        $this->ticketsStore = new JsonStore($ticketsPath);
        $this->ensureSpotsSeeded();
    }

    public function enterVehicle(string $plate, string $type): string
    {
        $plate = strtoupper(trim($plate));
        $type = $this->normalizeType($type);
        if ($plate === '') {
            throw new \InvalidArgumentException('Plate is required');
        }
        $spots = $this->loadSpots();
        $availableSpot = null;
        foreach ($spots as $spot) {
            if ($spot->type === $type && $spot->occupiedTicketId === null) {
                $availableSpot = $spot;
                break;
            }
        }
        if ($availableSpot === null) {
            throw new \RuntimeException('No available spots for type: ' . $type);
        }
        $ticketId = $this->generateTicketId();
        $ticket = new Ticket($ticketId, $plate, $type, $availableSpot->id, time(), null, null);
        $availableSpot->occupiedTicketId = $ticketId;
        $this->saveTicket($ticket);
        $this->saveSpots($spots);
        return $ticketId;
    }

    public function exitVehicle(string $ticketId): float
    {
        $ticketId = trim($ticketId);
        $tickets = $this->loadTickets();
        $ticket = null;
        foreach ($tickets as $t) {
            if ($t->id === $ticketId) {
                $ticket = $t;
                break;
            }
        }
        if ($ticket === null) {
            throw new \RuntimeException('Ticket not found');
        }
        if ($ticket->exitTs !== null) {
            throw new \RuntimeException('Ticket already closed');
        }
        $exitTs = time();
        $fee = $this->calculateFee($ticket->type, $ticket->entryTs, $exitTs);
        $ticket->exitTs = $exitTs;
        $ticket->fee = $fee;
        $this->saveTickets($tickets);
        $spots = $this->loadSpots();
        foreach ($spots as $spot) {
            if ($spot->id === $ticket->spotId) {
                $spot->occupiedTicketId = null;
                break;
            }
        }
        $this->saveSpots($spots);
        return $fee;
    }

    public function listActiveTickets(): array
    {
        $tickets = $this->loadTickets();
        $out = [];
        foreach ($tickets as $t) {
            if ($t->exitTs === null) {
                $out[] = [
                    'id' => $t->id,
                    'plate' => $t->plate,
                    'type' => $t->type,
                    'spot_id' => $t->spotId,
                    'entry_ts' => $t->entryTs,
                ];
            }
        }
        return $out;
    }

    public function getDashboardStats(): array
    {
        $spots = $this->loadSpots();
        $tickets = $this->loadTickets();
        $total = count($spots);
        $occupied = 0;
        foreach ($spots as $s) {
            if ($s->occupiedTicketId !== null) {
                $occupied++;
            }
        }
        $active = 0;
        foreach ($tickets as $t) {
            if ($t->exitTs === null) {
                $active++;
            }
        }
        return [
            'total_spots' => $total,
            'available_spots' => $total - $occupied,
            'occupied_spots' => $occupied,
            'active_tickets' => $active,
        ];
    }

    private function calculateFee(string $type, int $entryTs, int $exitTs): float
    {
        $durationSeconds = max(0, $exitTs - $entryTs);
        $hours = (int) ceil($durationSeconds / 3600);
        $hours = max(1, $hours);
        $rate = match ($type) {
            'bike' => 1.00,
            'car' => 2.50,
            'truck' => 4.00,
            default => 2.50,
        };
        return $hours * $rate;
    }

    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        return in_array($type, ['car', 'bike', 'truck'], true) ? $type : 'car';
    }

    private function loadSpots(): array
    {
        $rows = $this->spotsStore->read();
        $spots = [];
        foreach ($rows as $r) {
            $spots[] = Spot::fromArray($r);
        }
        return $spots;
    }

    private function saveSpots(array $spots): void
    {
        $rows = [];
        foreach ($spots as $s) {
            $rows[] = $s->toArray();
        }
        $this->spotsStore->write($rows);
    }

    private function loadTickets(): array
    {
        $rows = $this->ticketsStore->read();
        $tickets = [];
        foreach ($rows as $r) {
            $tickets[] = Ticket::fromArray($r);
        }
        return $tickets;
    }

    private function saveTickets(array $tickets): void
    {
        $rows = [];
        foreach ($tickets as $t) {
            $rows[] = $t->toArray();
        }
        $this->ticketsStore->write($rows);
    }

    private function saveTicket(Ticket $ticket): void
    {
        $tickets = $this->loadTickets();
        $tickets[] = $ticket;
        $this->saveTickets($tickets);
    }

    private function ensureSpotsSeeded(): void
    {
        $rows = $this->spotsStore->read();
        if (count($rows) > 0) {
            return;
        }
        $seed = [];
        $id = 1;
        for ($i = 0; $i < 20; $i++) {
            $seed[] = (new Spot($id++, 'car'))->toArray();
        }
        for ($i = 0; $i < 10; $i++) {
            $seed[] = (new Spot($id++, 'bike'))->toArray();
        }
        for ($i = 0; $i < 5; $i++) {
            $seed[] = (new Spot($id++, 'truck'))->toArray();
        }
        $this->spotsStore->write($seed);
    }

    private function generateTicketId(): string
    {
        // Simple sequential id based on count; good enough for demo
        $existing = $this->loadTickets();
        $next = count($existing) + 1;
        return 'TCK-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}



