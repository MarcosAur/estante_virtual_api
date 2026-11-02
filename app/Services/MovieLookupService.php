<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MovieLookupService
{
    private const GENRES = [
        28 => 'Ação',
        12 => 'Aventura',
        16 => 'Animação',
        35 => 'Comédia',
        80 => 'Crime',
        99 => 'Documentário',
        18 => 'Drama',
        10751 => 'Família',
        14 => 'Fantasia',
        36 => 'História',
        27 => 'Terror',
        10402 => 'Música',
        9648 => 'Mistério',
        10749 => 'Romance',
        878 => 'Ficção científica',
        10770 => 'Cinema TV',
        53 => 'Thriller',
        10752 => 'Guerra',
        37 => 'Faroeste',
    ];

    public function search(?string $query = null): array
    {
        $q = trim($query ?? '');
        if (empty($q)) {
            return [];
        }

        $apiKey = config('services.tmdb.api_key');
        if (empty($apiKey)) {
            return [];
        }

        $baseUrl = rtrim(config('services.tmdb.base_url', 'https://api.themoviedb.org/3'), '/');
        $cacheKey = 'movies_lookup_' . md5($q);

        return Cache::remember($cacheKey, 3600, function () use ($q, $apiKey, $baseUrl) {
            try {
                $client = Http::timeout(5)->acceptJson();

                $params = [
                    'query' => $q,
                    'include_adult' => false,
                    'language' => 'pt-BR',
                    'page' => 1,
                ];

                // If key is a JWT (v4 Bearer token)
                if (str_starts_with($apiKey, 'eyJ')) {
                    $client = $client->withToken($apiKey);
                } else {
                    $params['api_key'] = $apiKey;
                }

                $response = $client->get("{$baseUrl}/search/movie", $params);

                if (!$response->successful()) {
                    return [];
                }

                $data = $response->json();
                $items = $data['results'] ?? [];

                $results = [];
                foreach (array_slice($items, 0, 10) as $item) {
                    $releaseYear = null;
                    if (!empty($item['release_date']) && strlen($item['release_date']) >= 4) {
                        $releaseYear = (int) substr($item['release_date'], 0, 4);
                    }

                    $posterUrl = null;
                    if (!empty($item['poster_path'])) {
                        $posterUrl = 'https://image.tmdb.org/t/p/w500' . $item['poster_path'];
                    }

                    $genreNames = [];
                    if (!empty($item['genre_ids']) && is_array($item['genre_ids'])) {
                        foreach ($item['genre_ids'] as $gid) {
                            if (isset(self::GENRES[$gid])) {
                                $genreNames[] = self::GENRES[$gid];
                            }
                        }
                    }

                    $results[] = [
                        'tmdb_id' => (string) ($item['id'] ?? ''),
                        'title' => $item['title'] ?? '',
                        'director' => null,
                        'year' => $releaseYear,
                        'runtime_minutes' => 0,
                        'genre' => !empty($genreNames) ? implode(', ', array_slice($genreNames, 0, 3)) : null,
                        'synopsis' => $item['overview'] ?? null,
                        'poster_url' => $posterUrl,
                    ];
                }

                return $results;
            } catch (\Throwable $e) {
                return [];
            }
        });
    }
}
