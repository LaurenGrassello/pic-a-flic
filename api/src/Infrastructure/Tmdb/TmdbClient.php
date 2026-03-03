<?php
declare(strict_types=1);

namespace PicaFlic\Infrastructure\Tmdb;

use Psr\Http\Client\ClientInterface as HttpClient;
use Psr\Http\Message\RequestFactoryInterface as RequestFactory;

final class TmdbClient
{
    public function __construct(
        private string $apiKey,
        private HttpClient $http,
        private RequestFactory $req
    ) {}

    private function get(string $url, array $qs = []): array {
        $qs['api_key'] = $this->apiKey;
        $q = http_build_query($qs);
        $req = $this->req->createRequest('GET', $url . (str_contains($url, '?') ? '&' : '?') . $q)
                         ->withHeader('Accept', 'application/json');
        $res = $this->http->sendRequest($req);
        $json = json_decode((string)$res->getBody(), true);
        return is_array($json) ? $json : [];
    }

    public function watchProviders(string $type = 'movie', string $region = 'US'): array {
        $data = $this->get("https://api.themoviedb.org/3/watch/providers/{$type}", [
            'watch_region' => $region,
        ]);
        return $data['results'] ?? [];
    }

    public function titleProviders(string $type, int $tmdbId, string $region = 'US'): array {
        // /movie/{id}/watch/providers OR /tv/{id}/watch/providers
        $data = $this->get("https://api.themoviedb.org/3/{$type}/{$tmdbId}/watch/providers");
        $r = $data['results'][$region] ?? [];
        // Merge the common “included in subscription” buckets
        $buckets = array_merge($r['flatrate'] ?? [], $r['ads'] ?? [], $r['free'] ?? []);
        // Normalize
        $out = [];
        foreach ($buckets as $p) {
            $out[] = [
                'id'        => (int)($p['provider_id'] ?? 0),
                'name'      => (string)($p['provider_name'] ?? ''),
                'logo_path' => $p['logo_path'] ?? null,
            ];
        }
        // de-dupe by id
        $uniq = [];
        foreach ($out as $p) { $uniq[$p['id']] = $p; }
        return array_values($uniq);
    }

    public function discover(string $type, array $params): array {
        return $this->get("https://api.themoviedb.org/3/discover/{$type}", $params);
    }

    /** Search specific media type: movie | tv */
    public function search(string $type, string $query, array $opts = []): array {
        $query = trim($query);
        if ($query === '') {
            return ['page' => 1, 'results' => [], 'total_pages' => 1];
        }
        $qs = array_merge([
            'query'         => $query,
            'include_adult' => 'false',
        ], $opts);
        return $this->get("https://api.themoviedb.org/3/search/{$type}", $qs);
    }

    /** Multi search across types (we’ll filter to movie/tv in the controller) */
    public function searchMulti(string $query, int $page = 1, string $region = 'US'): array {
        $query = trim($query);
        if ($query === '') {
            return ['page' => 1, 'results' => [], 'total_pages' => 1];
        }
        return $this->get('https://api.themoviedb.org/3/search/multi', [
            'query'         => $query,
            'page'          => $page,
            'include_adult' => 'false',
            'region'        => $region,
        ]);
    }
}