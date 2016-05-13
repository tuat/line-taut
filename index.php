<?php
require_once __DIR__.'/vendor/autoload.php';

use Slim\App;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Monolog\Handler\StreamHandler;
use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\GuzzleHTTPClient;
use LINE\LINEBot\Message\MultipleMessages;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Providers\Tautr\TautrServiceProvider;

$app = new App([
    'settings' => require_once __DIR__.'/config.php'
]);

$container = $app->getContainer();

$container['logger'] = function($c) {
    $settings = $c->get('settings');

    $logger = new Logger($settings['logger']['name']);
    $logger->pushProcessor(new UidProcessor());
    $logger->pushHandler(new StreamHandler($settings['logger']['path'], Logger::DEBUG));

    return $logger;
};

$container['bot'] = function($c) {
    $settings  = $c->get('settings');
    $botConfig = [
        'channelId'     => $settings['bot']['channelId'],
        'channelSecret' => $settings['bot']['channelSecret'],
        'channelMid'    => $settings['bot']['channelMid'],
    ];

    return new LINEBot($botConfig, new GuzzleHTTPClient($botConfig));
};

$container['tautr'] = function($c) {
    $settings = $c->get('settings');

    return new TautrServiceProvider($settings['tautr']['apiKey']);
};

$app->get('/', function(Request $request, Response $response, $args) {
    return $response->write('Hello world');
});

$app->post('/callback', function(Request $request, Response $response, $args) {
    // Get data
    $body = $request->getBody();

    // Header channel signature checking
    $channelSignature = $request->getHeader('X-LINE-ChannelSignature');

    if (empty($channelSignature) === true || $this->bot->validateSignature($body, $channelSignature[0]) === false) {
        return $response->withStatus(400, "Bad Request")->write("400 Bad Request");
    }

    // Line bot
    $bot = $this->bot;

    // Receive messages
    $receives = $bot->createReceivesFromJSON($body);

    foreach ($receives as $receive) {
        if ($receive->isOperation() === true) {
            $this->logger->info(sprintf(
                'type=%s, revision=%s, fromMid=%s',
                'Operation',
                $receive->getRevision(),
                $receive->getFromMid()
            ));

            if ($receive->isAddContact() === true) {
                $this->logger->info("=> Add Contact");

                $bot->sendText($receive->getFromMid(), "Thank you for adding me to your contact list! more info please send: @tautr help");
            }else if ($receive->isBlockContact() === true) {
                $this->logger->info("=> Blocked");
            }else{
                $this->logger->info("=> Invalid operation type");
            }
        }

        if ($receive->isMessage() === true) {
            $this->logger->info(sprintf(
                'type=%s, contentId=%s, fromMid=%s, createdTime=%s',
                'Message',
                $receive->getContentId(),
                $receive->getFromMid(),
                $receive->getCreatedTime()
            ));

            if ($receive->isText() === true) {
                if ($receive->getText() === "@tautr help") {
                    $bot->sendText($receive->getFromMid(), sprintf(
                        "Available commands:\n%s",
                        implode("\n", [
                            '- tautr image',
                            '- tautr images'
                        ])
                    ));
                }

                if ($receive->getText() === '@tautr image') {
                    $imageUrl = $this->tautr->randomImage();

                    $bot->sendImage($receive->getFromMid(), $imageUrl, $imageUrl);

                    $this->logger->info(sprintf(
                        "=> image=%s",
                        $imageUrl
                    ));
                }

                if ($receive->getText() === '@tautr images') {
                    $imageUrls = $this->tautr->randomImages();

                    $messages = new MultipleMessages();

                    foreach($imageUrls as $imageUrl) {
                        $messages->addImage($imageUrl, $imageUrl);

                        $this->logger->info(sprintf(
                            "=> image=%s",
                            $imageUrl
                        ));
                    }

                    $bot->sendMultipleMessages($receive->getFromMid(), $messages);
                }
            }
        }
    }

    return $response->write("OK");
});

$app->run();
