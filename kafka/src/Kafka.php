<?php

namespace Xpkg\Kafka;

use App\Models\Target;
use Xpkg\Http\Http;

class Kafka
{
    public static string $connection = 'main';
    public string $topic = '';
    public array $data = [];
    public ?string $key = null;
    
    public static function profileModified(int $profileID, array $profileSource = [])
    {
        /*if (empty($profileSource)) {
            $profileSource = Target::where('profileID', '=', $profileID)->first()->toArray();
        }
        static::init()
            ->withKey((string)$profileID)
            ->withData($profileSource ?: [])
            ->topic(Topic::PROFILES)
            ->send();*/
        $kafkaUrl = "http://192.168.20.111:8080/v1/profile/add_profile_kafka?profileID={$profileID}";
        Http::get($kafkaUrl)->sendAndForget();
    }
    
    public function send()
    {
        $this->buildRequest()->sendAndForget();
    }
    
    public function buildRequest(): Http
    {
        $connection = static::$connection;
        $config = config("kafka.{$connection}");
        $config['host'] = trim($config['host'], ' /:');
        $config['prefix'] = trim($config['prefix'], ' /:');
        $url = "{$config['host']}:{$config['port']}/{$config['prefix']}";
        
        return Http::post($url, $this->buildDataArray());
    }
    
    public function buildDataArray(): array
    {
        return [
            'Key'   => empty($this->key) ? 0 : $this->key,
            'Topic' => $this->topic,
            'Value' => json_encode($this->data),
        ];
    }
    
    public function topic(Topic $topic): static
    {
        $this->topic = $topic->value;
        return $this;
    }
    
    public function withData(array $data): static
    {
        $this->data = $data;
        return $this;
    }
    
    public function withKey(string $key): static
    {
        $this->key = $key;
        return $this;
    }
    
    public static function init(): static
    {
        return new static();
    }
    
    public static function profileCreated(int $profileID)
    {
        $kafkaUrl = "http://192.168.20.111:8080/v1/profile/add_profile_kafka?profileID={$profileID}";
        Http::get($kafkaUrl)->sendAndForget();
    }
    
}