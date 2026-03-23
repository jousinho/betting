<?php

declare(strict_types=1);

namespace App\Infrastructure\Betting\Http\Client;

use App\Domain\Betting\Repository\OddsProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TheOddsApiClient implements OddsProviderInterface
{
    private const BASE_URL    = 'https://api.the-odds-api.com/v4';
    private const BULK_MARKETS  = 'h2h,totals';
    private const EVENT_MARKETS = 'btts,double_chance';
    private const REGION      = 'eu';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
    ) {}

    public function fetchBulkOdds(string $sportKey): array
    {
        $data = $this->get(sprintf('/sports/%s/odds', $sportKey), [
            'regions'     => self::REGION,
            'markets'     => self::BULK_MARKETS,
            'oddsFormat'  => 'decimal',
        ]);

        $indexed = [];
        foreach ($data as $event) {
            $indexed[$event['id']] = [
                'home_team'  => $event['home_team'],
                'away_team'  => $event['away_team'],
                'bookmakers' => $this->normalizeBookmakers($event['bookmakers'] ?? []),
            ];
        }

        return $indexed;
    }

    public function fetchEventOdds(string $eventId, string $sportKey): array
    {
        $data = $this->get(sprintf('/sports/%s/events/%s/odds', $sportKey, $eventId), [
            'regions'    => self::REGION,
            'markets'    => self::EVENT_MARKETS,
            'oddsFormat' => 'decimal',
        ]);

        return [
            'bookmakers' => $this->normalizeBookmakers($data['bookmakers'] ?? []),
        ];
    }

    private function normalizeBookmakers(array $bookmakers): array
    {
        $result = [];
        foreach ($bookmakers as $bookmaker) {
            $key = $bookmaker['key'];
            $result[$key] = [];
            foreach ($bookmaker['markets'] as $market) {
                $result[$key][$market['key']] = $market['outcomes'];
            }
        }

        return $result;
    }

    private function get(string $path, array $query = []): array
    {
        $query['apiKey'] = $this->apiKey;

        $response = $this->httpClient->request('GET', self::BASE_URL . $path, [
            'query' => $query,
        ]);

        return $response->toArray();
    }
}
