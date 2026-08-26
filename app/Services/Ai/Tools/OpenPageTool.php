<?php

namespace App\Services\Ai\Tools;

use App\Models\Ai\AgentMessage;
use App\Models\ProductCategory;
use App\Services\Ai\AgentContext;
use App\Services\Ai\Tour\AgentPageCatalog;
use App\Services\Ai\Tour\AgentTourCatalog;
use Illuminate\Support\Str;

class OpenPageTool extends AbstractAgentTool
{
    /**
     * Query keys the target list pages already honor.
     *
     * @var list<string>
     */
    protected const ALLOWED_FILTER_KEYS = [
        'category_id',
        'variant_search',
        'sku',
        'product',
        'q',
        'search',
    ];

    public function __construct(
        protected AgentPageCatalog $pages,
        protected AgentTourCatalog $tour,
    ) {}

    public function name(): string
    {
        return 'open_page';
    }

    public function description(): string
    {
        return 'Open an admin page. MUST be called when the user asks to buka/pergi ke/tunjukkan/arahkan ke '
            .'a page (including "buka halamannya dong"). When they also name a filter '
            .'(buka stok minuman, filter kategori X, cari SKU), pass category and/or search — '
            .'the widget opens a URL with those query params already applied. '
            .'Do not tell the user to type in the search box. Do not say you cannot navigate. '
            .'query = menu or topic (category, items, stock, POS, division, …). '
            .'For relative "halamannya"/"yang tadi", pass the page implied by the last topic. '
            .'Only menus the user can see are allowed. This is not a product tour.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['query', 'search', 'category', 'new_tab'],
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Page name or topic: category, items, stock, pos, division, '
                        .'or the implied page for "halamannya". Indonesian names are fine (kategori, stok, kasir). '
                        .'May include leftover filter words (stok minuman) if search/category are empty.',
                ],
                'search' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional list search text (SKU or product name). '
                        .'Stock page → variant_search. Product items → product. Null if none.',
                ],
                'category' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional category name or id (e.g. Minuman). Resolved to category_id '
                        .'on stock and product lists. Null if none.',
                ],
                'new_tab' => [
                    'type' => ['boolean', 'null'],
                    'description' => 'True = new browser tab. False = same tab. Null = new tab when a filter is set, else same tab.',
                ],
            ],
        ];
    }

    public function requiredPermission(): ?array
    {
        return null;
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));

        if ($query === '') {
            $query = 'halamannya';
        }

        $page = $this->pages->resolve($query, $context, $this->conversationHaystack($context));

        if ($page === null) {
            return [
                'success' => false,
                'needs_navigation' => false,
                'message' => 'Halaman itu tidak ketemu. Coba sebut namanya, misalnya kategori, item, stok, atau POS.',
            ];
        }

        $path = $this->pages->safePath($page['url'] ?? null);

        if ($path === null) {
            return [
                'success' => false,
                'needs_navigation' => false,
                'message' => 'Halaman itu tidak punya alamat yang aman untuk dibuka.',
            ];
        }

        if (! $this->pages->canAccess($page, $context)) {
            return [
                'success' => false,
                'needs_navigation' => false,
                'label' => (string) $page['label'],
                'message' => 'Kamu tidak punya izin membuka halaman '.$page['label'].'.',
            ];
        }

        $search = $this->nullableString($arguments['search'] ?? null);
        $category = $this->nullableString($arguments['category'] ?? null);

        if ($search === null && $category === null) {
            $leftover = $this->leftoverFilterText($query, $page);

            if ($leftover !== '') {
                $category = $leftover;
            }
        }

        $filters = $this->filterParams($path, $search, $category);
        $url = $this->withQuery($path, $filters);
        $newTab = $this->wantsNewTab($arguments['new_tab'] ?? null, $filters !== []);
        $alreadyThere = $filters === [] && $this->tour->sameLocation((string) $context->pagePath, $path);
        $label = (string) $page['label'];
        $filterNote = $this->filterNote($filters, $search, $category);

        return [
            'success' => true,
            'url' => $url,
            'label' => $label,
            'filters' => $filters,
            'new_tab' => $newTab && ! $alreadyThere,
            'needs_navigation' => ! $alreadyThere,
            'already_there' => $alreadyThere,
            'message' => $alreadyThere
                ? 'User sudah di halaman '.$label.'. Jangan bilang gagal navigasi. Chat 1 kalimat.'
                : ($newTab
                    ? 'Widget akan membuka halaman '.$label.$filterNote.' di tab baru. Ucapkan 1 kalimat bahwa halaman itu dibuka dengan filter. Jangan minta user mengetik di kotak filter. Jangan bilang user harus klik menu sendiri.'
                    : 'Widget akan membuka halaman '.$label.$filterNote.'. Ucapkan 1 kalimat bahwa halaman itu dibuka. Jangan minta user mengetik di kotak filter. Jangan bilang user harus klik menu sendiri.'),
        ];
    }

    /**
     * @param  array{label: string, url: string, menu_names: list<string>, aliases: list<string>}  $page
     */
    protected function leftoverFilterText(string $query, array $page): string
    {
        $text = $this->pages->stripFiller($query);
        $needles = array_merge(
            $page['aliases'] ?? [],
            $page['menu_names'] ?? [],
            [(string) ($page['label'] ?? '')],
            [
                'filter', 'filters', 'kategori', 'category',
                'dengan', 'untuk', 'yang', 'sudah', 'pada',
                'berdasarkan', 'berdasar', 'saring', 'cari', 'search',
            ],
        );
        usort($needles, static fn ($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));

        foreach ($needles as $needle) {
            $needle = mb_strtolower(trim((string) $needle));

            if ($needle === '' || mb_strlen($needle) < 2) {
                continue;
            }

            $quoted = preg_quote($needle, '/');
            $text = preg_replace('/(^|[^\p{L}\p{N}])'.$quoted.'([^\p{L}\p{N}]|$)/u', '$1 $2', $text) ?? $text;
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @return array<string, string>
     */
    protected function filterParams(string $path, ?string $search, ?string $category): array
    {
        $categoryId = $category !== null ? $this->resolveCategoryId($category) : null;

        if ($category !== null && $categoryId === null) {
            $search = $search ?? $category;
        }

        $normalized = $this->tour->normalizePath($path);

        $params = match ($normalized) {
            '/product/stock' => array_filter([
                'category_id' => $categoryId,
                'variant_search' => $search,
            ]),
            '/product/items' => array_filter([
                'category_id' => $categoryId,
                'product' => $search,
            ]),
            default => array_filter([
                'category_id' => $categoryId,
                'q' => $search,
            ]),
        };

        return $this->onlyAllowedKeys($params);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function withQuery(string $path, array $params): string
    {
        $params = $this->onlyAllowedKeys($params);

        if ($params === []) {
            return $path;
        }

        return $path.'?'.http_build_query($params);
    }

    protected function resolveCategoryId(?string $value): ?string
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        if (Str::isUuid($value)) {
            return $value;
        }

        try {
            $id = ProductCategory::query()
                ->whereNull('deleted_at')
                ->where(function ($query) use ($value) {
                    $query->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                        ->orWhereRaw('LOWER(code) = ?', [mb_strtolower($value)]);
                })
                ->value('id');
        } catch (\Throwable) {
            return null;
        }

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @param  array<string, string>  $filters
     */
    protected function filterNote(array $filters, ?string $search, ?string $category): string
    {
        if ($filters === []) {
            return '';
        }

        $bits = [];

        if (isset($filters['category_id'])) {
            $bits[] = 'kategori '.($category ?? $filters['category_id']);
        }

        $searchValue = $filters['variant_search'] ?? $filters['product'] ?? $filters['q'] ?? $search;

        if (is_string($searchValue) && $searchValue !== '') {
            $bits[] = 'pencarian '.$searchValue;
        }

        return $bits === [] ? ' dengan filter' : ' ('.implode(', ', $bits).')';
    }

    protected function wantsNewTab(mixed $explicit, bool $hasFilters): bool
    {
        if (is_bool($explicit)) {
            return $explicit;
        }

        if (is_string($explicit)) {
            $lower = mb_strtolower(trim($explicit));

            if (in_array($lower, ['1', 'true', 'yes', 'ya'], true)) {
                return true;
            }

            if (in_array($lower, ['0', 'false', 'no', 'tidak'], true)) {
                return false;
            }
        }

        return $hasFilters;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, string>
     */
    protected function onlyAllowedKeys(array $params): array
    {
        $out = [];

        foreach (self::ALLOWED_FILTER_KEYS as $key) {
            if (! array_key_exists($key, $params)) {
                continue;
            }

            $value = $params[$key];

            if (! is_scalar($value) || $value === '' || $value === null) {
                continue;
            }

            $out[$key] = (string) $value;
        }

        return $out;
    }

    protected function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function conversationHaystack(AgentContext $context): string
    {
        if ($context->conversationId === null) {
            return '';
        }

        try {
            $rows = AgentMessage::query()
                ->where('conversation_id', $context->conversationId)
                ->whereIn('role', ['user', 'assistant', 'tool'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(12)
                ->get(['content']);
        } catch (\Throwable) {
            return '';
        }

        $parts = [];

        foreach ($rows as $row) {
            $content = trim((string) $row->content);

            if ($content !== '') {
                $parts[] = $content;
            }
        }

        return implode(' ', $parts);
    }
}
