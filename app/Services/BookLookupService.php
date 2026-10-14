<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BookLookupService
{
    public function search(?string $isbn = null, ?string $query = null): array
    {
        $q = '';
        if ($isbn) {
            $cleanedIsbn = preg_replace('/[^0-9X]/i', '', $isbn);
            $q = "isbn:{$cleanedIsbn}";
        } elseif ($query) {
            $q = trim($query);
        }

        if (empty($q)) {
            return [];
        }

        $cacheKey = 'books_lookup_' . md5($q);

        return Cache::remember($cacheKey, 3600, function () use ($q) {
            try {
                $response = Http::timeout(5)->get('https://www.googleapis.com/books/v1/volumes', [
                    'q' => $q,
                    'maxResults' => 10,
                    'printType' => 'books',
                ]);

                if (!$response->successful()) {
                    return [];
                }

                $data = $response->json();
                $items = $data['items'] ?? [];

                $results = [];
                foreach ($items as $item) {
                    $info = $item['volumeInfo'] ?? [];
                    
                    $publishedYear = null;
                    if (!empty($info['publishedDate'])) {
                        $publishedYear = (int) substr($info['publishedDate'], 0, 4);
                    }

                    $foundIsbn = null;
                    if (!empty($info['industryIdentifiers'])) {
                        foreach ($info['industryIdentifiers'] as $identifier) {
                            if (in_array($identifier['type'] ?? '', ['ISBN_13', 'ISBN_10'])) {
                                $foundIsbn = $identifier['identifier'] ?? null;
                                break;
                            }
                        }
                    }

                    $thumbnail = $info['imageLinks']['thumbnail'] ?? $info['imageLinks']['smallThumbnail'] ?? null;
                    if ($thumbnail && str_starts_with($thumbnail, 'http://')) {
                        $thumbnail = 'https://' . substr($thumbnail, 7);
                    }

                    $results[] = [
                        'title' => $info['title'] ?? '',
                        'author' => !empty($info['authors']) ? implode(', ', $info['authors']) : '',
                        'publisher' => $info['publisher'] ?? null,
                        'year' => $publishedYear,
                        'pages' => $info['pageCount'] ?? 0,
                        'synopsis' => $info['description'] ?? null,
                        'isbn' => $foundIsbn,
                        'cover_url' => $thumbnail,
                    ];
                }

                return $results;
            } catch (\Throwable $e) {
                return [];
            }
        });
    }
}
