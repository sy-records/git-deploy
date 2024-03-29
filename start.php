<?php
/**
 * This file is part of git-deploy.
 *
 * @link     https://github.com/sy-records/git-deploy
 * @document https://github.com/sy-records/git-deploy
 * @license  https://github.com/sy-records/git-deploy/blob/master/LICENSE
 */

use Swoole\Coroutine;
use Swoole\Http\Server;
use Swoole\Runtime;

class start
{
    public $name = 'git-deploy';

    /** @var Server */
    protected $_server;

    protected $_config;

    public function __construct()
    {
        $config = self::getConfig();
        $this->_config = $config;
        $this->_server = new Server($config['server']['ip'], $config['server']['port'], $config['server']['mode']);
        $this->_server->set($config['server']['settings']);
        $this->_server->on('workerStart', [$this, 'onWorkerStart']);
        $this->_server->on('request', [$this, 'onRequest']);
        $this->_server->start();
    }

    public function onWorkerStart(Server $server, int $workerId)
    {
        @cli_set_process_title($this->name . " #{$workerId}");
    }

    public function onRequest(Swoole\Http\Request $request, Swoole\Http\Response $response)
    {
        try {
            $header = $request->header;
            $data = $request->rawContent();
            $msg = '';
            switch ($request->server['request_uri']) {
                case '/github':
                    $msg = $this->github($data, $header);
                    break;
                case '/gitee':
                    $msg = $this->gitee($data, $header);
                    break;
                default:
                    $response->status(404);
            }
        } catch (\Throwable $e) {
            $response->status(403);
            $msg = $e->getMessage();
        }
        return $response->end($msg);
    }

    public function getConfig()
    {
        return json_decode(file_get_contents(__DIR__ . '/config.json'), true);
    }

    public function github($data, $header)
    {
        if (isset($header['x-github-event']) && $header['x-github-event'] === 'ping') {
            return 'success';
        }

        $content = json_decode($data, true);

        if (isset($this->_config['sites']['github'][$content['repository']['full_name']])) {
            $config = $this->_config['sites']['github'][$content['repository']['full_name']];
        } else {
            throw new RuntimeException('config does not exist');
        }

        if (isset($config['secret']) && ! empty($config['secret'])) {
            $signature = $header['x-hub-signature'] ?? '';
            if (! $signature) {
                goto error;
            }
            [$algo, $hash] = explode('=', $signature, 2);
            $payloadHash = hash_hmac($algo, $data, $config['secret']);
            if ($hash !== $payloadHash) {
                goto error;
            }
        }

        if ($config['ref'] === $content['ref'] && $config['event_name'] === $header['x-github-event']) {
            foreach ($config['shells'] as $cmd) {
                Coroutine::create(function () use($cmd) {
                    Coroutine::exec($cmd);
                });
            }

            return 'finished';
        }

        error:
        throw new RuntimeException('verification failure');
    }

    public function gitee($data, $header)
    {
        if (isset($header['x-gitee-ping']) && $header['x-gitee-ping'] === 'true') {
            return 'success';
        }

        $content = json_decode($data, true);

        if (isset($this->_config['sites']['gitee'][$content['project']['path_with_namespace']])) {
            $config = $this->_config['sites']['gitee'][$content['project']['path_with_namespace']];
        } else {
            throw new RuntimeException('config does not exist');
        }

        if (isset($config['password']) && ! empty($config['password'])) {
            $signature = $header['x-gitee-token'] ?? '';
            if (! $signature || $signature !== $config['password']) {
                goto error;
            }
        }

        if (isset($config['secret']) && ! empty($config['secret'])) {
            $signature = $header['x-gitee-token'] ?? '';
            $timestamp = $header['x-gitee-timestamp'] ?? '';
            if (! $signature || ! $timestamp) {
                goto error;
            }
            $secret_str = "$timestamp\n{$config['secret']}";
            $compute_token = base64_encode(hash_hmac('sha256', $secret_str, $config['secret'],true));
            if ($signature !== $compute_token) {
                goto error;
            }
        }

        if ($config['ref'] === $content['ref'] && $config['event_name'] === $content['hook_name']) {
            foreach ($config['shells'] as $cmd) {
                Coroutine::create(function () use($cmd) {
                    Coroutine::exec($cmd);
                });
            }

            return 'finished';
        }

        error:
        throw new RuntimeException('verification failure');
    }
}

Runtime::enableCoroutine(SWOOLE_HOOK_ALL);
(new start());
