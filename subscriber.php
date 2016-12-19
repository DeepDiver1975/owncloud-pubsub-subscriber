<?php
use React\EventLoop\Factory;
use React\Socket\Server;
use React\Http\Request;
use React\Http\Response;
require __DIR__ . '/vendor/autoload.php';

// config section start

// the owncloud server you want to connect to
$server = "http://localhost:8080";
$username = 'admin';
$password = 'admin';
// config section end

function startServer($loop) {
    $socket = new Server($loop);
    $server = new \React\Http\Server($socket);
    $server->on('request', function (Request $request, Response $response) {
        // handle request
        $headers = $request->getHeaders();
        $event = isset($headers['X-ownCloud-Event']) ? $headers['X-ownCloud-Event'] : 'unknown';
        echo "Event received: $event" . PHP_EOL;
        $request->on('data', function($data) use ($request, $response, $event) {
        	if ($event === 'owncloud://quota') {
        		$d = json_decode($data, true);
        		echo "User: {$d['user']} is now using {$d['usedPercent']}% of his storage." . PHP_EOL;
			} else {
                echo $data.PHP_EOL;
            }
        });

        // respond properly
        $response->writeHead();
        $response->end();
    });
    $host = gethostname();
    $port = 12345;
    $socket->listen($port, $host);
    $url = "http://$host:" . $socket->getPort();
    echo "Listening on $url" . PHP_EOL;
    return $url;
}

function subscribe($server, $callback, $username, $password) {
    $client = new \GuzzleHttp\Client();
    $client->post("$server/index.php/apps/web_hooks/hub", [
        'auth' => [
            $username, $password
        ],
        'form_params' => [
            'hub.mode' => 'subscribe',
            'hub.callback' => $callback,
            'hub.topic' => 'owncloud://quota'
        ]
    ]);
}

$loop = Factory::create();
$callback = startServer($loop);
subscribe($server, $callback, $username, $password);

$loop->run();
