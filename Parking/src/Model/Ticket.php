<?php
declare(strict_types=1);

namespace App\Model;

final class Ticket
{
    public string $id;
    public string $plate;
    public string $type; // car | bike | truck
    public int $spotId;
    public int $entryTs;
    public ?int $exitTs;
    public ?float $fee;

    public function __construct(string $id, string $plate, string $type, int $spotId, int $entryTs, ?int $exitTs = null, ?float $fee = null)
    {
        $this->id = $id;
        $this->plate = $plate;
        $this->type = $type;
        $this->spotId = $spotId;
        $this->entryTs = $entryTs;
        $this->exitTs = $exitTs;
        $this->fee = $fee;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            (string) $row['id'],
            (string) $row['plate'],
            (string) $row['type'],
            (int) $row['spot_id'],
            (int) $row['entry_ts'],
            isset($row['exit_ts']) ? (int) $row['exit_ts'] : null,
            isset($row['fee']) ? (float) $row['fee'] : null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'plate' => $this->plate,
            'type' => $this->type,
            'spot_id' => $this->spotId,
            'entry_ts' => $this->entryTs,
            'exit_ts' => $this->exitTs,
            'fee' => $this->fee,
        ];
    }
}



