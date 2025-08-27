<?php
declare(strict_types=1);

namespace App\Storage;

final class JsonStore
{
    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (!is_file($filePath)) {
            file_put_contents($filePath, json_encode([]));
        }
    }

    public function read(): array
    {
        $fp = fopen($this->filePath, 'c+');
        if ($fp === false) {
            return [];
        }
        flock($fp, LOCK_SH);
        $size = filesize($this->filePath);
        $raw = $size > 0 ? fread($fp, $size) : '';
        flock($fp, LOCK_UN);
        fclose($fp);
        if ($raw === '' || $raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public function write(array $data): void
    {
        $fp = fopen($this->filePath, 'c+');
        if ($fp === false) {
            throw new \RuntimeException('Failed to open store: ' . $this->filePath);
        }
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}



