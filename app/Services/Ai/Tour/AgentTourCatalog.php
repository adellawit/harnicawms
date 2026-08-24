<?php

namespace App\Services\Ai\Tour;

class AgentTourCatalog
{
    /**
     * @return array<int, string>
     */
    public function tourKeys(): array
    {
        return array_values(array_filter(
            (array) config('agent_tour.tour', []),
            fn ($key) => is_string($key) && $key !== '',
        ));
    }

    public function headingSelector(): string
    {
        return (string) config('agent_tour.heading_selector', '.content-wrapper .head-label h4, .content-wrapper .card-header, #layout-navbar');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function children(string $key): array
    {
        $room = $this->room($key);

        if ($room === null) {
            return [];
        }

        $children = [];

        foreach ((array) ($room['children'] ?? []) as $child) {
            if (! is_array($child)) {
                continue;
            }

            $label = trim((string) ($child['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $children[] = [
                'label' => $label,
                'blurb' => (string) ($child['blurb'] ?? ''),
                'voice' => (string) ($child['voice'] ?? ''),
                'url' => (string) ($child['url'] ?? ''),
                'selector' => (string) ($child['selector'] ?? ''),
                'menu_names' => array_values(array_filter(
                    (array) ($child['menu_names'] ?? [$label]),
                    fn ($name) => is_string($name) && $name !== '',
                )),
            ];
        }

        return $children;
    }

    public function pageKind(?string $path): string
    {
        $normalized = $this->normalizePath($path);

        if (preg_match('#/(insert|edit|create|update|add)(/|$)#i', $normalized) === 1) {
            return 'form';
        }

        return 'index';
    }

    /**
     * @param  array<string, mixed>|null  $room
     * @return list<array<string, mixed>>
     */
    public function pageSpots(string $kind, ?array $room = null): array
    {
        $kind = $kind === 'form' ? 'form' : 'index';
        $override = is_array($room) ? (array) ($room['page_spots'][$kind] ?? []) : [];
        $generic = (array) config('agent_tour.page_spots.'.$kind, []);
        $source = $override !== [] ? $override : $generic;

        return array_values(array_filter(
            $source,
            fn ($spot) => is_array($spot) && trim((string) ($spot['key'] ?? $spot['label'] ?? '')) !== '',
        ));
    }

    /**
     * @param  array<string, mixed>|null  $room
     * @return list<array<string, mixed>>
     */
    public function overviewPageSpots(?array $room): array
    {
        $allowed = array_values(array_filter(
            (array) config('agent_tour.overview_page_spot_keys', ['title', 'add']),
            fn ($key) => is_string($key) && $key !== '',
        ));
        $limit = max(0, (int) config('agent_tour.overview_page_spot_limit', 2));
        $spots = [];

        foreach ($this->pageSpots('index', $room) as $spot) {
            $key = (string) ($spot['key'] ?? '');

            if ($key === '' || ! in_array($key, $allowed, true)) {
                continue;
            }

            $spots[] = $spot;

            if (count($spots) >= $limit) {
                break;
            }
        }

        return $spots;
    }

    /**
     * @return array<string, mixed>
     */
    public function room(string $key): ?array
    {
        $rooms = (array) config('agent_tour.rooms', []);
        $room = $rooms[$key] ?? null;

        if (! is_array($room)) {
            return null;
        }

        return array_merge($room, ['key' => $key]);
    }

    /**
     * @return array<string, mixed>
     */
    public function match(?string $path, ?string $title = null, ?string $menu = null): array
    {
        $normalized = $this->normalizePath($path);
        $rooms = (array) config('agent_tour.rooms', []);
        $matchedKey = null;
        $bestLength = -1;

        foreach ($rooms as $key => $room) {
            if (! is_array($room)) {
                continue;
            }

            foreach ((array) ($room['prefixes'] ?? []) as $prefix) {
                $prefixPath = $this->normalizePath((string) $prefix);

                if ($prefixPath === '/') {
                    continue;
                }

                if ($normalized === $prefixPath || str_starts_with($normalized, $prefixPath.'/')) {
                    $length = strlen($prefixPath);

                    if ($length > $bestLength) {
                        $bestLength = $length;
                        $matchedKey = (string) $key;
                    }
                }
            }
        }

        if ($matchedKey === null) {
            $matchedKey = $this->matchMenu($menu, $rooms);
        }

        if ($matchedKey === null) {
            return $this->unknownRoom($normalized, $title, $menu);
        }

        return $this->payload($matchedKey, $normalized, $title, $menu);
    }

    /**
     * @param  array<string, mixed>  $rooms
     */
    protected function matchMenu(?string $menu, array $rooms): ?string
    {
        $needle = mb_strtolower(trim((string) $menu));

        if ($needle === '') {
            return null;
        }

        foreach ($rooms as $key => $room) {
            if (! is_array($room)) {
                continue;
            }

            foreach ((array) ($room['menu_names'] ?? []) as $name) {
                $candidate = mb_strtolower(trim((string) $name));

                if ($candidate !== '' && ($candidate === $needle || str_contains($needle, $candidate))) {
                    return (string) $key;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(string $key, ?string $path = null, ?string $title = null, ?string $menu = null): array
    {
        $room = $this->room($key);

        if ($room === null) {
            return $this->unknownRoom($path, $title, $menu);
        }

        return [
            'key' => $key,
            'label' => (string) ($room['label'] ?? $key),
            'path' => $this->normalizePath($path),
            'title' => $title,
            'menu' => $menu,
            'url' => (string) ($room['url'] ?? '/'),
            'selector' => (string) ($room['selector'] ?? '#layout-menu .menu-item.active > a.menu-link'),
            'heading_selector' => $this->headingSelector(),
            'blurb' => (string) ($room['blurb'] ?? ''),
            'voice' => (string) ($room['voice'] ?? ''),
            'menu_names' => array_values(array_filter(
                (array) ($room['menu_names'] ?? []),
                fn ($name) => is_string($name) && $name !== '',
            )),
            'docs_query' => (string) ($room['docs_query'] ?? $key),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function unknownRoom(?string $path, ?string $title, ?string $menu): array
    {
        $label = trim((string) ($menu ?: $title ?: 'Halaman ini'));
        $queryParts = array_filter([$menu, $title, $path]);

        return [
            'key' => 'unknown',
            'label' => $label !== '' ? $label : 'Halaman ini',
            'path' => $this->normalizePath($path),
            'title' => $title,
            'menu' => $menu,
            'url' => $this->normalizePath($path),
            'selector' => '#layout-menu .menu-item.active > a.menu-link',
            'heading_selector' => $this->headingSelector(),
            'blurb' => 'Ini halaman yang sedang kamu buka.',
            'voice' => 'Aku REDDIE. Ini halaman yang sedang kamu buka.',
            'menu_names' => [],
            'docs_query' => implode(' ', $queryParts) ?: 'modul halaman admin TITANIE',
        ];
    }

    public function normalizePath(?string $path): string
    {
        $value = trim((string) $path);

        if ($value === '') {
            return '/';
        }

        if (str_contains($value, '://')) {
            $parsed = parse_url($value, PHP_URL_PATH);
            $value = is_string($parsed) ? $parsed : '/';
        }

        $value = explode('?', $value, 2)[0];
        $value = '/'.ltrim($value, '/');

        if ($value !== '/') {
            $value = rtrim($value, '/');
        }

        return $value === '' ? '/' : $value;
    }

    public function sameLocation(string $currentPath, string $targetUrl): bool
    {
        $current = $this->normalizePath($currentPath);
        $target = $this->normalizePath($targetUrl);

        return $current === $target || str_starts_with($current, $target.'/');
    }
}
