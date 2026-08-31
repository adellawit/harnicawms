<?php

namespace App\Services\Ai\Tour;

use App\Models\Menu;
use App\Services\Ai\AgentContext;
use Illuminate\Support\Facades\Route;

class AgentPageCatalog
{
    public function __construct(
        protected AgentTourCatalog $tour,
    ) {}

    /**
     * @return list<array{label: string, url: string, menu_names: list<string>, aliases: list<string>}>
     */
    public function pages(): array
    {
        $byUrl = [];

        foreach ($this->fromTour() as $page) {
            $this->mergePage($byUrl, $page);
        }

        foreach ($this->fromRecords() as $page) {
            $this->mergePage($byUrl, $page);
        }

        foreach ($this->fromMenus() as $page) {
            $this->mergePage($byUrl, $page);
        }

        return array_values($byUrl);
    }

    public function isRelativeQuery(string $query): bool
    {
        $stripped = $this->stripFiller($query);

        return $stripped === '' || in_array($stripped, [
            'itu',
            'nya',
            'tersebut',
            'terkait',
            'tadi',
            'yang tadi',
            'ini',
            'dulu',
            'saja',
            'aja',
        ], true);
    }

    /**
     * @return array{label: string, url: string, menu_names: list<string>, aliases: list<string>}|null
     */
    public function resolve(string $query, AgentContext $context, string $conversationText = ''): ?array
    {
        $pages = $this->pages();

        if ($pages === []) {
            return null;
        }

        if ($this->isRelativeQuery($query)) {
            $fromTopic = $this->bestMatch($pages, $conversationText);

            if ($fromTopic !== null) {
                return $fromTopic;
            }

            return $this->bestMatch(
                $pages,
                trim(implode(' ', array_filter([
                    $context->pageMenu,
                    $context->pageTitle,
                    $context->pagePath,
                ]))),
            );
        }

        $fromQuery = $this->bestMatch($pages, $query);

        if ($fromQuery !== null) {
            return $fromQuery;
        }

        return $this->bestMatch($pages, $query.' '.$conversationText);
    }

    /**
     * @param  array{label: string, url: string, menu_names: list<string>, aliases: list<string>}  $page
     */
    public function canAccess(array $page, AgentContext $context): bool
    {
        $names = $page['menu_names'] ?? [];

        if ($names === []) {
            $names = [(string) ($page['label'] ?? '')];
        }

        foreach ($names as $name) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            if ($context->hasPermission(['menu' => $name, 'action' => 'is_read'])) {
                return true;
            }

            foreach (array_keys($context->permissions) as $granted) {
                if (is_string($granted) && strcasecmp($granted, $name) === 0
                    && (int) ($context->permissions[$granted]['is_read'] ?? 0) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    public function safePath(?string $url): ?string
    {
        $path = $this->tour->normalizePath($url);

        if ($path === '' || str_contains($path, '..') || str_contains($path, '://') || str_starts_with($path, '//')) {
            return null;
        }

        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return $path;
    }

    /**
     * @return list<array{label: string, url: string, menu_names: list<string>, aliases: list<string>}>
     */
    protected function fromTour(): array
    {
        $pages = [];
        $rooms = (array) config('agent_tour.rooms', []);

        foreach ($rooms as $room) {
            if (! is_array($room)) {
                continue;
            }

            $landing = $room;
            $landing['menu_names'] = array_values(array_filter([
                trim((string) ($room['label'] ?? '')),
            ]));
            $pages[] = $this->entryFromMap($landing);

            foreach ((array) ($room['children'] ?? []) as $child) {
                if (is_array($child)) {
                    $pages[] = $this->entryFromMap($child);
                }
            }
        }

        return array_values(array_filter($pages));
    }

    /**
     * @return list<array{label: string, url: string, menu_names: list<string>, aliases: list<string>}>
     */
    protected function fromRecords(): array
    {
        $pages = [];
        $entities = (array) config('agent_records.entities', []);
        $aliases = (array) config('agent_records.aliases', []);
        $urlByMenu = [];

        foreach ($this->fromTour() as $page) {
            foreach ($page['menu_names'] as $name) {
                $urlByMenu[mb_strtolower($name)] = $page['url'];
            }
        }

        foreach ($entities as $key => $entity) {
            if (! is_array($entity)) {
                continue;
            }

            $menu = trim((string) ($entity['menu'] ?? ''));
            $label = trim((string) ($entity['label'] ?? $menu));
            $url = $urlByMenu[mb_strtolower($menu)] ?? $urlByMenu[mb_strtolower($label)] ?? '';

            if ($url === '' || $menu === '') {
                continue;
            }

            $pageAliases = [$key, $label, $menu];

            foreach ($aliases as $alias => $entityKey) {
                if ((string) $entityKey === (string) $key) {
                    $pageAliases[] = (string) $alias;
                }
            }

            $pages[] = $this->makePage($label !== '' ? $label : $menu, $url, [$menu, $label], $pageAliases);
        }

        return $pages;
    }

    /**
     * @return list<array{label: string, url: string, menu_names: list<string>, aliases: list<string>}>
     */
    protected function fromMenus(): array
    {
        try {
            $menus = Menu::query()
                ->whereNotNull('url_path')
                ->where('url_path', '!=', '')
                ->get(['name', 'text_sidebar', 'url_path', 'route_name']);
        } catch (\Throwable) {
            return [];
        }

        $pages = [];

        foreach ($menus as $menu) {
            $url = $this->urlFromMenu($menu);

            if ($url === null) {
                continue;
            }

            $name = trim((string) $menu->name);
            $sidebar = trim((string) $menu->text_sidebar);
            $label = $sidebar !== '' ? $sidebar : $name;

            if ($label === '') {
                continue;
            }

            $pages[] = $this->makePage($label, $url, array_filter([$name, $sidebar]), [$name, $sidebar, $label]);
        }

        return $pages;
    }

    protected function urlFromMenu(Menu $menu): ?string
    {
        $path = trim((string) $menu->url_path);

        if ($path !== '') {
            return $this->safePath('/'.ltrim($path, '/'));
        }

        $routeName = trim((string) $menu->route_name);

        if ($routeName !== '' && Route::has($routeName)) {
            try {
                return $this->safePath((string) route($routeName, [], false));
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array{label: string, url: string, menu_names: list<string>, aliases: list<string>}|null
     */
    protected function entryFromMap(array $map): ?array
    {
        $url = $this->safePath((string) ($map['url'] ?? ''));
        $label = trim((string) ($map['label'] ?? ''));

        if ($url === null || $label === '') {
            return null;
        }

        $menuNames = array_values(array_filter(
            (array) ($map['menu_names'] ?? [$label]),
            fn ($name) => is_string($name) && $name !== '',
        ));

        return $this->makePage($label, $url, $menuNames, array_merge($menuNames, [$label]));
    }

    /**
     * @param  list<string>  $menuNames
     * @param  list<string>  $aliases
     * @return array{label: string, url: string, menu_names: list<string>, aliases: list<string>}
     */
    protected function makePage(string $label, string $url, array $menuNames, array $aliases): array
    {
        $menuNames = $this->uniqueStrings($menuNames);
        $aliases = $this->uniqueStrings(array_merge($aliases, $menuNames, [$label], $this->aliasesForUrl($url)));

        return [
            'label' => $label,
            'url' => $url,
            'menu_names' => $menuNames !== [] ? $menuNames : [$label],
            'aliases' => $aliases,
        ];
    }

    /**
     * Extra search aliases for well-known admin URLs (open_page).
     *
     * @return list<string>
     */
    protected function aliasesForUrl(string $url): array
    {
        return match ($this->tour->normalizePath($url)) {
            '/product/category' => ['kategori', 'category', 'kategori produk', 'product category'],
            '/product/items' => ['items', 'item', 'barang', 'produk', 'product', 'product item', 'sku'],
            '/product/stock' => ['stok', 'stock', 'stok gudang'],
            '/product/stock-opname' => ['stock opname', 'opname'],
            '/product/stock-adjustment' => ['stock adjustment', 'penyesuaian stok', 'adjustment'],
            '/product/purchase-order' => ['purchase order', 'po', 'pembelian', 'purchasing', 'penerimaan barang', 'penerimaan'],
            '/transaction/pos' => ['pos', 'kasir', 'point of sale'],
            '/human-resources/division' => ['divisi', 'division'],
            '/human-resources/position' => ['jabatan', 'position'],
            '/human-resources/employee' => ['karyawan', 'employee', 'pegawai'],
            '/customer/list' => ['customer', 'pelanggan', 'customer list'],
            '/customer/group' => ['group', 'grup', 'grup pelanggan'],
            '/product/satuan' => ['satuan', 'unit'],
            '/product/price-list' => ['price list', 'daftar harga', 'pricelist'],
            '/dashboard' => ['dashboard', 'beranda'],
            default => [],
        };
    }

    /**
     * @param  array<string, array{label: string, url: string, menu_names: list<string>, aliases: list<string>}>  $byUrl
     * @param  array{label: string, url: string, menu_names: list<string>, aliases: list<string>}|null  $page
     */
    protected function mergePage(array &$byUrl, ?array $page): void
    {
        if ($page === null) {
            return;
        }

        $url = $page['url'];

        if (! isset($byUrl[$url])) {
            $byUrl[$url] = $page;

            return;
        }

        $byUrl[$url]['menu_names'] = $this->uniqueStrings(array_merge($byUrl[$url]['menu_names'], $page['menu_names']));
        $byUrl[$url]['aliases'] = $this->uniqueStrings(array_merge($byUrl[$url]['aliases'], $page['aliases']));

        $incoming = $page['label'];
        $existing = $byUrl[$url]['label'];

        if (strlen($incoming) > strlen($existing) && $incoming !== '') {
            $byUrl[$url]['label'] = $incoming;
        }
    }

    /**
     * @param  list<array{label: string, url: string, menu_names: list<string>, aliases: list<string>}>  $pages
     * @return array{label: string, url: string, menu_names: list<string>, aliases: list<string>}|null
     */
    protected function bestMatch(array $pages, string $haystack): ?array
    {
        $text = mb_strtolower(trim($haystack));

        if ($text === '') {
            return null;
        }

        $best = null;
        $bestScore = 0;

        foreach ($pages as $page) {
            $score = $this->scorePage($page, $text);

            if ($score > $bestScore || ($score === $bestScore && $score > 0 && $this->isBetterTie($page, $best, $text))) {
                $bestScore = $score;
                $best = $page;
            }
        }

        return $bestScore > 0 ? $best : null;
    }

    /**
     * @param  array{label: string, url: string, menu_names: list<string>, aliases: list<string>}  $page
     */
    protected function scorePage(array $page, string $haystack): int
    {
        $best = 0;
        $label = mb_strtolower(trim((string) ($page['label'] ?? '')));

        if ($label !== '' && $haystack === $label) {
            $best = 2000 + mb_strlen($label);
        } elseif ($label !== '' && mb_strlen($label) >= 8 && str_contains($label, ' ')) {
            $quotedLabel = preg_quote($label, '/');
            if (preg_match('/(^|[^\p{L}\p{N}])'.$quotedLabel.'([^\p{L}\p{N}]|$)/u', $haystack) === 1) {
                $best = 400 + mb_strlen($label);
            }
        }

        foreach ($page['aliases'] as $alias) {
            $needle = mb_strtolower(trim($alias));

            if ($needle === '' || mb_strlen($needle) < 2) {
                continue;
            }

            if ($haystack === $needle) {
                $best = max($best, 1000 + mb_strlen($needle));

                continue;
            }

            $quoted = preg_quote($needle, '/');

            if (preg_match('/(^|[^\p{L}\p{N}])'.$quoted.'([^\p{L}\p{N}]|$)/u', $haystack) === 1) {
                $best = max($best, 100 + mb_strlen($needle));
            }
        }

        return $best;
    }

    /**
     * @param  array{label: string, url: string, menu_names: list<string>, aliases: list<string>}|null  $current
     * @param  array{label: string, url: string, menu_names: list<string>, aliases: list<string>}  $candidate
     */
    protected function isBetterTie(array $candidate, ?array $current, string $haystack): bool
    {
        if ($current === null) {
            return true;
        }

        $candidateLabel = mb_strtolower(trim((string) ($candidate['label'] ?? '')));
        $currentLabel = mb_strtolower(trim((string) ($current['label'] ?? '')));

        if ($candidateLabel === $haystack && $currentLabel !== $haystack) {
            return true;
        }

        $candidateUrl = (string) ($candidate['url'] ?? '');
        $currentUrl = (string) ($current['url'] ?? '');

        return strlen($candidateUrl) > strlen($currentUrl);
    }

    public function stripFiller(string $query): string
    {
        $text = mb_strtolower(trim($query));
        $text = preg_replace('/[?.!,]+/u', ' ', $text) ?? $text;
        $fillers = [
            'tolong', 'please', 'dong', 'ya', 'yuk', 'yok', 'lah', 'sih',
            'buka', 'bukain', 'bukakan', 'bukainnya',
            'pergi', 'pergiin', 'pergi ke',
            'tunjukkan', 'tunjukin', 'arahkan', 'arahin',
            'bawa', 'bawain', 'lihat', 'liatin', 'tampilkan', 'tampilin',
            'halamannya', 'halaman nya', 'halaman itu', 'halaman tersebut',
            'halaman terkait', 'halaman ini', 'halaman',
            'page', 'open', 'show', 'go to', 'goto', 'navigate',
            'ke', 'ke-', 'the', 'a', 'an',
        ];
        usort($fillers, static fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($fillers as $filler) {
            $quoted = preg_quote($filler, '/');
            $text = preg_replace('/(^|[^\p{L}\p{N}])'.$quoted.'([^\p{L}\p{N}]|$)/u', '$1 $2', $text) ?? $text;
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @param  list<mixed>  $values
     * @return list<string>
     */
    protected function uniqueStrings(array $values): array
    {
        $seen = [];
        $out = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);

            if ($trimmed === '') {
                continue;
            }

            $key = mb_strtolower($trimmed);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = $trimmed;
        }

        return $out;
    }
}
