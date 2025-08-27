<?php

namespace Xpkg\Http;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Support\Facades\Log;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class Http
{
    protected static \Closure|null $closure = null;
    protected static Client $client;
    protected $url;
    protected int $timeout = 60;
    protected int $connectTimeout = 10;
    protected bool $verify = false;
    protected $query = [];
    protected $body = [];
    protected $method = 'GET';
    protected $authBasic = [];
    protected $headers = [];
    protected $success = [];
    protected $errors = [];
    protected $command = '';
    protected string $logChannel = 'http';

    public static function setBeforeRequest(\Closure $closure)
    {
        static::$closure = $closure;
    }

    public static function getBeforeRequestClosure()
    {
        return static::$closure;
    }

    public function __construct(array $options = [])
    {
        $this->withMethod('GET')
            ->withUrl('')
            ->withBody([])
            ->withHeaders(['Content-Type' => 'application/json'])
            ->withBasicAuthentication([])
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->verify(false)
            ->withQuery([]);
        if (empty(static::$client)) {
            $options = array_merge([
                'verify'  => false,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ], $options);
            static::$client = new Client($options);
        }
    }
    
    public function withQuery(array $query): static
    {
        $this->query = $query;
        return $this;
    }
    
    public function verify(bool $verify): static
    {
        $this->verify = $verify;
        return $this;
    }
    
    public function connectTimeout(int $seconds): static
    {
        $this->connectTimeout = $seconds;
        return $this;
    }
    
    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;
        return $this;
    }
    
    public function withBasicAuthentication($username, $password = null): static
    {
        if (!is_array($username) && $password !== null) {
            $username = [$username, $password];
        }
        $this->authBasic = $username;
        return $this;
    }
    
    public function withHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }
    
    public function withBody($body): static
    {
        $this->body = $body;
        return $this;
    }

    public function logChannel(string $channel): static
    {
        $this->logChannel = $channel;
    }
    
    public function withUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }
    
    public function withMethod($method): static
    {
        $method = trim(strtoupper($method));
        $allowedMethods = ['GET', 'POST', 'PUT', 'HEAD', 'OPTIONS', 'DELETE'];
        if (!in_array($method, $allowedMethods)) {
            throw new MethodNotAllowedException($allowedMethods, "$method is not allowed");
        }
        $this->method = $method;
        return $this;
    }
    
    public static function get($url)
    {
        return static::make('GET', $url);
    }
    
    public static function make($method = 'GET', $url = '')
    {
        $instance = new static();
        return $instance->withMethod($method)->withUrl($url);
    }
    
    public static function post($url, array $body)
    {
        return static::make('POST', $url)->withBody($body);
    }
    
    public static function put($url, array $body)
    {
        return static::make('PUT', $url)->withBody($body);
    }
    
    public static function delete($url)
    {
        return static::make('DELETE', $url);
    }
    
    public function decode(): static
    {
        return $this->then(function ($res) {
            if (is_string($res)) {
                return json_decode($res, 1);
            }
            return $res;
        });
    }
    
    public function then(null|callable $success, null|callable $error = null): static
    {
        $this->success[] = $success;
        if (!empty($error)) {
            $this->errors[] = $error;
        }
        return $this;
    }
    
    public function getQuery()
    {
        return $this->query;
    }
    
    public function getHeaders(): array
    {
        return $this->headers;
    }
    
    public function getUrl()
    {
        return $this->url;
    }
    
    public function getMethod(): string
    {
        return $this->method;
    }
    
    public function getBasicAuthentication(): array
    {
        return $this->authBasic;
    }

    public function dd()
    {
        dd($this->buildCmd());
    }

    public function run($debug = false)
    {
        if ($debug === true || $debug === 1) {
            dd($this->buildCmd());
        }
        try {
            $promise = $this->buildPromise();
            $this->runBeforeFunc($promise);
            return $promise->wait();
        } catch (\Throwable $e) {
            return '';
        }
    }
    
    public function buildCmd(): string
    {
        $options = $this->buildPromise(true);
        $needAuth = false;
        $user = '';
        $pass = '';
        if (!empty($options['auth']) && count($options['auth']) === 2) {
            $needAuth = true;
            [$user, $pass] = $options['auth'];
        }
        $url = new Uri($options['url']);
        if ($needAuth) {
            $url = $url->withUserInfo($user, $pass);
        }
        $url = (string)$url;
        $method = $options['method'];
        $body = $options['body'];
        $headers = $this->headers;
        $connectTimeout = $options['connect_timeout'];
        $maxTime = $connectTimeout + $options['timeout'];
        $extra = '';
        //$seperator = md5('---###!!!SEPERATOR!!!###---');
        if ($method === 'HEAD') {
            $method = 'GET';
            $extra = '-o /dev/null ';
        }
        //$extra .= "-w '\n{$seperator}%{url_effective}\n%{http_code}'";
        $curl = "curl -X{$method} -k -s -L ";
        $curl .= "--connect-timeout {$connectTimeout} ";
        $curl .= "--max-time {$maxTime} ";
        foreach ($headers as $key => $header) {
            $curl .= "-H '{$key}: {$header}' ";
        }
        
        $curl .= "--url '{$url}' ";
        
        $curl .= "{$extra} ";
        if (!empty($body)) {
            $json = is_array($body) ? json_encode($body, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : $body;
            $curl .= "-d '$json'";
        }
        return $curl;
    }
    
    protected function buildPromise(bool $forCmd = false): PromiseInterface|array
    {
        ini_set('memory_limit', '-1');
        $request = new Request($this->method, $this->url);
        
        $body = $this->body;
        if (!is_string($body)) {
            if (is_object($body) && method_exists($body, 'toArray')) {
                $body = $body->toArray();
            }
            if ($body instanceof JsonSerializable || is_array($body)) {
                $body = empty($body) ? '' : json_encode($body);
                //$body = json_encode($body);
            }
        }
        
        
        $options = [
            'auth'            => $this->authBasic,
            'connect_timeout' => $this->connectTimeout,
            'headers'         => $this->headers,
            'query'           => $this->query,
            'verify'          => $this->verify,
            'timeout'         => $this->timeout,
            'body'            => $body,
        ];
        if ($forCmd) {
            $options['method'] = strtoupper(trim($this->method));
            $options['url'] = $this->url;
            return $options;
        }
        
        $this->command = $this->buildCmd();
        return static::$client->sendAsync($request, $options)->then(function (ResponseInterface $res) {
            Log::channel($this->logChannel)->info($this->command);
            $response = $res->getBody()->getContents();
            foreach ($this->success as $success) {
                $response = $success($response);
            }
            return $response;
        }, function (Throwable $e) {
            $msg = $e->getMessage();
            if (method_exists($e, 'getResponse')) {
                $msg = $e->getResponse()->getBody()->getContents();
            }
            
            Log::channel($this->logChannel)->error("{$this->command}\n{$msg}");
            throw new Exception($msg, 503);
        });
    }
    
    public function getBody()
    {
        return $this->body;
    }
    
    public function runCmd()
    {
        $cmd = $this->buildCmd();
        //$cmd = escapeshellcmd($cmd);
        $this->runBeforeFunc($cmd);
        Log::channel($this->logChannel)->info($cmd);
        $res = shell_exec($cmd);
        return $res;
    }
    
    public function sendAndForget()
    {
        $cmd = $this->buildCmd();
        $cmd .= ' > /dev/null &';
        $this->runBeforeFunc($cmd);
        Log::channel($this->logChannel)->info($cmd);
        pclose(popen($cmd, 'r'));
    }

    protected function runBeforeFunc($data = null)
    {
        if(is_callable(static::$closure)) {
            $fn = static::$closure;
            $fn($data);
        }
    }
}