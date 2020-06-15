<?php
/**
 * This file is part of git-deploy.
 *
 * @link     https://github.com/sy-records/git-deploy
 * @document https://github.com/sy-records/git-deploy
 * @license  https://github.com/sy-records/git-deploy/blob/master/LICENSE
 */

use Swoole\Runtime;
use Swoole\Coroutine;
use Swoole\Http\Server;

class start
{
    /** @var Server $_server */
    protected $_server;

    protected $_config;

    public $name = 'git-deploy';

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

    public function onRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response)
    {
        try{
            $header = $request->header;
            $data = $request->rawContent();
            $msg = '';
            switch($request->server['request_uri'])
            {
                case '/github':
                    $msg = $this->github($data, $header, $response);
                    break;
                default:
                    $response->status(404);
            }
        } catch(\Throwable $e) {
            $response->status(403);
            $response->write($e->getMessage());
        }
        return $response->end($msg);
    }

    public function getConfig()
    {
        return json_decode(file_get_contents(__DIR__ . '/config.json'), true);
    }

    public function github($data, $header, $response)
    {
        $signature = $header['x-hub-signature'] ?? '';
        if (!$signature) {
            goto error;
        }

        if (isset($header['x-github-event']) && $header['x-github-event'] === 'ping') {
            return 'success';
        }

        if (isset($this->_config[$data['repository']['full_name']])) {
            $config = $this->_config[$data['repository']['full_name']];
        } else {
            throw new RuntimeException('config does not exist');
        }

        $content = json_decode($data, true);
        list($algo, $hash) = explode('=', $signature, 2);
        $payloadHash = hash_hmac($algo, $content, $config['secret']);

        if ($hash === $payloadHash && $config['ref'] === $data['ref'] && $config['event_name'] === $header['x-github-event']) {
            foreach($config['shells'] as $cmd)
            {
                Coroutine::exec($cmd);
            }

            return 'finished';
        }

        error:
        throw new RuntimeException('verification failure');
    }
}

Runtime::enableCoroutine(SWOOLE_HOOK_ALL);
(new start());