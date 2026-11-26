<?php

namespace App\Support\RecipeImport;

use App\Models\RecipeIngredientAssignment;
use App\Support\Ingredients\IngredientMatcher;
use App\Support\ShoppingList\Quantity;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads a recipe from a web page into a draft for the recipe form — never
 * into the database. Structured data first (schema.org Recipe in JSON-LD,
 * then microdata), a heading + list heuristic last. Ingredient lines are
 * split into amount / unit / name and the name is matched to an existing
 * ingredient the same way the recipe form does it.
 */
class RecipeImporter
{
    public const TIMEOUT_SECONDS = 10;

    public const MAX_BYTES = 3_000_000;

    /**
     * @return array{name: string, servings: ?int, ingredients: list<array>, steps: list<string>, link: string, host: string}
     *
     * @throws RecipeImportFailed
     */
    public function import(string $url): array
    {
        $url = trim($url);
        $this->assertFetchable($url);

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; RecipeImporter/1.0)', 'Accept' => 'text/html'])
                ->withOptions(['allow_redirects' => ['max' => 5, 'protocols' => ['http', 'https']]])
                ->get($url);
        } catch (Throwable) {
            throw new RecipeImportFailed('fetch');
        }

        if (! $response->successful() || strlen($response->body()) > self::MAX_BYTES) {
            throw new RecipeImportFailed('fetch');
        }

        $html = $response->body();
        $raw = collect([fn () => $this->fromJsonLd($html), fn () => $this->fromMicrodata($html), fn () => $this->fromHeuristics($html)])
            ->map(fn (\Closure $read) => $read())
            ->first(fn (?array $found) => $found && filled($found['name']) && ! empty(array_filter($found['ingredients'])));

        if (! $raw) {
            throw new RecipeImportFailed('parse');
        }

        $matcher = app(IngredientMatcher::class);

        return [
            'name' => Str::limit(trim(html_entity_decode($raw['name'])), 250, ''),
            'servings' => $this->servings($raw['yield'] ?? null),
            'ingredients' => collect($raw['ingredients'])
                ->map(fn ($line) => trim(html_entity_decode(strip_tags((string) $line))))
                ->filter()
                ->map(fn (string $line) => $this->ingredientLine($line, $matcher))
                ->values()->all(),
            'steps' => collect($raw['steps'] ?? [])
                ->map(fn ($step) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $step)))))
                ->filter()->values()->all(),
            'link' => $url,
            'host' => preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)),
        ];
    }

    /** http(s) only, and never an address inside the home network. */
    private function assertFetchable(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RecipeImportFailed('url');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if (! $ips && ! app()->runningUnitTests()) {
            throw new RecipeImportFailed('fetch');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RecipeImportFailed('url');
            }
        }
    }

    private function fromJsonLd(string $html): ?array
    {
        if (! preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches)) {
            return null;
        }

        foreach ($matches[1] as $json) {
            $data = json_decode(html_entity_decode(trim($json)), true);

            if ($recipe = $this->findRecipeNode($data)) {
                return [
                    'name' => $recipe['name'] ?? '',
                    'yield' => $recipe['recipeYield'] ?? null,
                    'ingredients' => (array) ($recipe['recipeIngredient'] ?? $recipe['ingredients'] ?? []),
                    'steps' => $this->flattenInstructions($recipe['recipeInstructions'] ?? []),
                ];
            }
        }

        return null;
    }

    private function findRecipeNode(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        $type = $data['@type'] ?? null;

        if ($type === 'Recipe' || (is_array($type) && in_array('Recipe', $type, true))) {
            return $data;
        }

        foreach ($data['@graph'] ?? (array_is_list($data) ? $data : []) as $node) {
            if ($found = $this->findRecipeNode($node)) {
                return $found;
            }
        }

        return null;
    }

    /** @return list<string> HowToStep / HowToSection / plain strings → steps */
    private function flattenInstructions(mixed $instructions): array
    {
        if (is_string($instructions)) {
            return preg_split('/\R+|(?<=\.)\s+(?=\d+\.\s)/u', $instructions) ?: [];
        }

        $steps = [];

        foreach ((array) $instructions as $item) {
            if (is_string($item)) {
                $steps[] = $item;
            } elseif (is_array($item) && isset($item['itemListElement'])) {
                $steps = [...$steps, ...$this->flattenInstructions($item['itemListElement'])];
            } elseif (is_array($item)) {
                $steps[] = $item['text'] ?? $item['name'] ?? '';
            }
        }

        return $steps;
    }

    private function fromMicrodata(string $html): ?array
    {
        $xpath = $this->xpath($html);
        $scope = $xpath?->query('//*[@itemtype and contains(@itemtype, "schema.org/Recipe")]')->item(0);

        if (! $scope) {
            return null;
        }

        // Only the recipe's own properties — not those of nested items (author, rating...).
        $props = fn (string $name) => collect(iterator_to_array($xpath->query('.//*[@itemprop="'.$name.'"]', $scope)))
            ->filter(function ($node) use ($scope) {
                for ($parent = $node->parentNode; $parent && $parent !== $scope; $parent = $parent->parentNode) {
                    if ($parent instanceof \DOMElement && $parent->hasAttribute('itemscope')) {
                        return false;
                    }
                }

                return true;
            })
            ->map(fn ($node) => trim($node->getAttribute('content') ?: $node->textContent))
            ->values()->all();

        return [
            'name' => $props('name')[0] ?? '',
            'yield' => $props('recipeYield')[0] ?? null,
            'ingredients' => $props('recipeIngredient') ?: $props('ingredients'),
            'steps' => $props('recipeInstructions'),
        ];
    }

    /** No structured data: the page title + the list under a heading that says "składniki" / "ingredients". */
    private function fromHeuristics(string $html): ?array
    {
        $xpath = $this->xpath($html);

        if (! $xpath) {
            return null;
        }

        $title = $xpath->query('//meta[@property="og:title"]/@content')->item(0)?->nodeValue
            ?? $xpath->query('//h1')->item(0)?->textContent ?? '';

        $listAfter = function (array $words) use ($xpath) {
            foreach ($xpath->query('//h1|//h2|//h3|//h4|//strong') as $heading) {
                if (! Str::contains(mb_strtolower($heading->textContent), $words)) {
                    continue;
                }

                // Every list item up to the next heading (e.g. "Ciasto" + "Nadzienie" lists).
                $items = [];

                foreach ($xpath->query('following::*', $heading) as $node) {
                    if (in_array($node->nodeName, ['h1', 'h2', 'h3', 'h4'], true)) {
                        break;
                    }

                    if ($node->nodeName === 'li' && in_array($node->parentNode?->nodeName, ['ul', 'ol'], true)) {
                        $items[] = trim(preg_replace('/\s+/u', ' ', $node->textContent));
                    }

                    if (count($items) > 60) {
                        break;
                    }
                }

                if ($items) {
                    return $items;
                }

            }

            return [];
        };

        return [
            'name' => trim($title),
            'yield' => null,
            'ingredients' => $listAfter(['składnik', 'ingredient']),
            'steps' => $listAfter(['przygotowanie', 'sposób', 'instructions', 'method']),
        ];
    }

    private function xpath(string $html): ?DOMXPath
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? new DOMXPath($dom) : null;
    }

    private const NUMBER = '\\d+(?:[.,]\\d+)?(?:\\s+i\\s+\\d+\\/\\d+|\\s*\\/\\s*\\d+)?';

    /** @return array{0: ?float, 1: ?string, 2: string} amount, unit, the rest (the name) */
    private function splitAmount(string $text): array
    {
        if (! preg_match_all('/'.self::NUMBER.'/u', $text, $numbers, PREG_OFFSET_CAPTURE)) {
            return [null, null, $text];
        }

        // The first number followed (within two words) by a known unit wins;
        // otherwise the first number, counted in pieces.
        $choice = null;

        foreach ($numbers[0] as [$number, $offset]) {
            $after = substr($text, $offset + strlen($number));
            $words = preg_split('/\s+/u', trim($after), 4);

            foreach (array_slice($words, 0, 2) as $i => $word) {
                $unit = Quantity::canonicalUnit(rtrim($word, '.,;:'));

                if (in_array($unit, RecipeIngredientAssignment::UNITS, true)) {
                    $choice = [$number, $offset, $unit, implode(' ', array_slice($words, $i + 1))];
                    break 2;
                }
            }
        }

        if (! $choice) {
            [$number, $offset] = $numbers[0][0];
            $choice = [$number, $offset, 'pcs', trim(substr($text, $offset + strlen($number)))];
        }

        [$number, $offset, $unit, $rest] = $choice;
        $before = trim(rtrim(substr($text, 0, $offset), ' -–:'));

        return [$this->number($number), $unit, $before !== '' ? $before : $rest];
    }

    /** "1,5" → 1.5, "1/2" → 0.5, "2 i 1/2" → 2.5 */
    private function number(string $text): ?float
    {
        $total = 0.0;

        foreach (preg_split('/\s+i\s+/u', trim($text)) as $part) {
            if (str_contains($part, '/')) {
                [$a, $b] = array_map(fn ($n) => (float) str_replace(',', '.', $n), explode('/', str_replace(' ', '', $part)));
                $total += $b ? $a / $b : 0;
            } else {
                $total += (float) str_replace(',', '.', $part);
            }
        }

        return $total > 0 ? $total : null;
    }

    /** "4", "4 porcje", "6 osób", "Serves 4" → 4; "około 1650 ml" or "16 sztuk" are not servings. */
    private function servings(mixed $yield): ?int
    {
        $yield = mb_strtolower(trim(is_array($yield) ? implode(' ', $yield) : (string) $yield));

        if (! preg_match('/(\d+)/', $yield, $m)) {
            return null;
        }

        $bare = preg_match('/^\d+$/', $yield);
        $portions = preg_match('/porcj|osob|osób|serv|people|person/u', $yield);

        return ($bare || $portions) && (int) $m[1] >= 1 && (int) $m[1] <= 30 ? (int) $m[1] : null;
    }

    /**
     * "800 g dyni hokkaido", "Cebula 1 sztuka - około 200 g", "2 i 1/2
     * szklanki mąki", "śmietanka 30 % 100 ml" → amount / unit / name, the
     * name matched to an existing ingredient ("dyni hokkaido" → "Dynia
     * hokkaido" when it exists). No readable amount → empty amount and the
     * original line kept as a hint.
     */
    private function ingredientLine(string $line, IngredientMatcher $matcher): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', strtr($line, ['½' => ' 1/2', '¼' => ' 1/4', '¾' => ' 3/4', '⅓' => ' 1/3'])));
        $text = preg_replace('/(?<=\s|^)(pół|pol)(?=\s)/iu', '1/2', $text);
        [$amount, $unit, $name] = $this->splitAmount($text);

        // "szczypta soli", "sól do smaku", "świeża dynia - np. piżmowa" → "soli" / "sól" / "świeża dynia"
        $name = trim(preg_replace('/\s+-\s.*$|\s+(po|ok\.?|około)$/u', '', $name)) ?: $name;
        $name = preg_replace('/^(szczypt[aey]|garś[cć]i?|odrobin[aey]|troch[eę]|kilka|pół|pol)\s+/iu', '', $name);
        $name = preg_replace('/\s+(do smaku|opcjonalnie)$/iu', '', $name);
        $name = trim(preg_replace('/\s*[(,].*$/u', '', $name)) ?: $name;
        // Only the same name (case / diacritics / inflection): "cukier puder" must not become "Cukier".
        $existing = $matcher->same($name) ?? $matcher->sameKey($name);

        return [
            'name' => $existing?->name ?? Str::ucfirst($name),
            'amount' => $amount !== null ? rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.') : '',
            'unit' => $unit ?? 'g',
            'original' => $amount === null ? $line : null,
            'isNew' => $existing === null,
        ];
    }
}
