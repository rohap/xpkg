<?php
declare(strict_types=1);

namespace App\Model;

final class Spot
{
    public int $id;
    public string $type; // car | bike | truck
    public ?string $occupiedTicketId;

    public function __construct(int $id, string $type, ?string $occupiedTicketId = null)
    {
        $this->id = $id;
        $this->type = $type;
        $this->occupiedTicketId = $occupiedTicketId;
    }

    public static function fromArray(array $row): self
    {
        return new self((int) $row['id'], (string) $row['type'], $row['occupied_ticket_id'] ?? null);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'occupied_ticket_id' => $this->occupiedTicketId,
        ];
    }
}



