<?php

namespace App\Support;

use MongoDB\Client;
use Throwable;

class MongoConnection
{
    private static ?Client $client = null;

    public static function selectCollection(string $collection): ?\MongoDB\Collection
    {
        $client = self::client();
        if ($client === null) {
            return null;
        }

        return $client
            ->selectDatabase((string) config('esp32.mongodb.database', 'espData'))
            ->selectCollection($collection);
    }

    private static function client(): ?Client
    {
        if (self::$client !== null) {
            return self::$client;
        }

        $uri = trim((string) config('esp32.mongodb.uri', ''));
        if ($uri === '') {
            return null;
        }

        try {
            self::$client = new Client($uri, [], [
                'serverSelectionTimeoutMS' => (int) config('esp32.mongodb.server_selection_timeout_ms', 1500),
                'connectTimeoutMS' => (int) config('esp32.mongodb.connect_timeout_ms', 1500),
                'socketTimeoutMS' => (int) config('esp32.mongodb.socket_timeout_ms', 3000),
            ]);
        } catch (Throwable) {
            self::$client = null;
        }

        return self::$client;
    }
}
