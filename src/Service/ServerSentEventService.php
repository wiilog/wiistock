<?php

namespace App\Service;


use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class ServerSentEventService {

    public const DASHBOARD_FEED_TOPIC = "dashboard-feed";
    public const PRODUCTION_REQUEST_UPDATE_TOPIC = "production-request-update";


    public function __construct(
        private readonly HubInterface $hub,
    ){}


    public function sendEvent(array|string $topics,array|string $data, bool $private = true): void {
        if (is_array($data)) {
            $data = json_encode($data, JSON_THROW_ON_ERROR);
        }

        $update = new Update(
            $topics,
            $data,
            $private
        );

        $this->hub->publish($update);
    }
}
