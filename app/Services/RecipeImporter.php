<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Imports a recipe from a web page's schema.org/Recipe JSON-LD.
 *
 * Fetches user-supplied URLs, so it guards against SSRF: http(s) on standard
 * ports only, every hop's host must resolve to public IPs, the connection is
 * pinned to the validated IP, and redirects are followed manually.
 */
class RecipeImporter
{
    private const MAX_BYTES = 2_000_000;

    private const MAX_REDIRECTS = 3;

    /** @return array<string, mixed> */
    public function import(string $url): array
    {
        $html = $this->fetch($url);

        $recipe = $this->findRecipe($html)
            ?? throw new InvalidArgumentException('No recipe data found on that page.');

        return $this->map($recipe, $url);
    }

    private function fetch(string $url): string
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            [$host, $port, $ip] = $this->validate($url);

            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; FamilyRecipeImporter/1.0)', 'Accept' => 'text/html'])
                ->withOptions([
                    'allow_redirects' => false,
                    'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]],
                ])
                ->get($url);

            if ($response->redirect()) {
                $location = $response->header('Location');
                $url = $this->absolutize($url, $location);

                continue;
            }

            if (! $response->successful()) {
                throw new InvalidArgumentException('Could not load that page.');
            }

            return substr($response->body(), 0, self::MAX_BYTES);
        }

        throw new InvalidArgumentException('Too many redirects.');
    }

    /** @return array{0: string, 1: int, 2: string} host, port, resolved public IP */
    protected function validate(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Enter a valid http(s) link.');
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (! in_array($port, [80, 443], true)) {
            throw new InvalidArgumentException('That port is not allowed.');
        }

        $ips = $this->resolve($host);
        if ($ips === []) {
            throw new InvalidArgumentException('Could not resolve that address.');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('That address is not allowed.');
            }
        }

        return [$host, $port, $ips[0]];
    }

    /** @return list<string> */
    protected function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        return array_values(array_filter(gethostbynamel($host) ?: []));
    }

    private function absolutize(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $p = parse_url($base);
        $origin = "{$p['scheme']}://{$p['host']}".(isset($p['port']) ? ":{$p['port']}" : '');

        return str_starts_with($location, '/') ? $origin.$location : $origin.'/'.$location;
    }

    /** @return array<string, mixed>|null */
    private function findRecipe(string $html): ?array
    {
        if (! preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches)) {
            return null;
        }

        foreach ($matches[1] as $json) {
            $data = json_decode(html_entity_decode(trim($json)), true);
            if (! is_array($data)) {
                continue;
            }
            if ($found = $this->searchNode($data)) {
                return $found;
            }
        }

        return null;
    }

    /** @param array<mixed> $node */
    private function searchNode(array $node): ?array
    {
        $type = $node['@type'] ?? null;
        if ($type !== null && in_array('Recipe', (array) $type, true)) {
            return $node;
        }

        foreach ($node['@graph'] ?? $node as $child) {
            if (is_array($child) && ($found = $this->searchNode($child))) {
                return $found;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function map(array $r, string $url): array
    {
        return [
            'name' => $this->text($r['name'] ?? ''),
            'description' => $this->text($r['description'] ?? ''),
            'ingredients' => array_map(
                fn ($line) => $this->parseIngredient($this->text($line)),
                array_values(array_filter((array) ($r['recipeIngredient'] ?? []), 'is_string')),
            ),
            'instructions' => implode("\n\n", $this->instructions($r['recipeInstructions'] ?? [])),
            'prep_time' => $this->minutes($r['prepTime'] ?? null),
            'cook_time' => $this->minutes($r['cookTime'] ?? null),
            'servings' => $this->servings($r['recipeYield'] ?? null),
            'image_url' => $this->image($r['image'] ?? null),
            'source_url' => $url,
        ];
    }

    private function text(mixed $v): string
    {
        return trim(html_entity_decode(strip_tags((string) (is_array($v) ? ($v[0] ?? '') : $v)), ENT_QUOTES | ENT_HTML5));
    }

    /** @return list<string> */
    private function instructions(mixed $v): array
    {
        if (is_string($v)) {
            return array_values(array_filter(array_map('trim', preg_split('/\R+/', $this->text($v)))));
        }

        $steps = [];
        foreach ((array) $v as $item) {
            if (is_string($item)) {
                $steps[] = $this->text($item);
            } elseif (is_array($item) && isset($item['itemListElement'])) {
                array_push($steps, ...$this->instructions($item['itemListElement']));
            } elseif (is_array($item) && isset($item['text'])) {
                $steps[] = $this->text($item['text']);
            }
        }

        return array_values(array_filter($steps));
    }

    private function minutes(mixed $iso): int
    {
        if (! is_string($iso) || ! preg_match('/^P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?)?$/i', $iso, $m)) {
            return 0;
        }

        return ((int) ($m[1] ?? 0)) * 1440 + ((int) ($m[2] ?? 0)) * 60 + (int) ($m[3] ?? 0);
    }

    private function servings(mixed $yield): int
    {
        $yield = is_array($yield) ? ($yield[0] ?? '') : $yield;

        return preg_match('/\d+/', (string) $yield, $m) ? max(1, (int) $m[0]) : 4;
    }

    private function image(mixed $img): ?string
    {
        if (is_array($img)) {
            $img = $img['url'] ?? ($img[0]['url'] ?? $img[0] ?? null);
        }

        return is_string($img) && preg_match('#^https?://#i', $img) ? $img : null;
    }

    /** @return array{name: string, quantity: string, unit: string, notes: string} */
    private function parseIngredient(string $line): array
    {
        $units = 'cups?|c|tbsps?|tablespoons?|tsps?|teaspoons?|oz|ounces?|lbs?|pounds?|g|grams?|kg|ml|l|liters?|cloves?|cans?|pinch|slices?|sticks?|packages?|pkg';
        if (preg_match('/^\s*((?:\d+\s+)?\d+(?:[\/.]\d+)?|[¼½¾⅓⅔])\s*('.$units.')?\b\.?\s*(?:of\s+)?(.+)$/iu', $line, $m)) {
            return ['name' => trim($m[3]), 'quantity' => trim($m[1]), 'unit' => strtolower($m[2] ?? ''), 'notes' => ''];
        }

        return ['name' => $line, 'quantity' => '', 'unit' => '', 'notes' => ''];
    }
}
