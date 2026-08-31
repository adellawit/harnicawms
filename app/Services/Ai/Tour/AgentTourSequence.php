<?php

namespace App\Services\Ai\Tour;

/**
 * Flatten room catalog into overlay steps.
 *
 * overview = sidebar parent + at most 2 page spots per module.
 * page_deep = parent + submenus + 3–6 spots on the current page.
 */
class AgentTourSequence
{
    public function __construct(protected AgentTourCatalog $catalog) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function overview(): array
    {
        $steps = [];

        foreach ($this->catalog->tourKeys() as $key) {
            $room = $this->catalog->room($key);

            if ($room === null) {
                continue;
            }

            $steps[] = $this->sidebarStep($room, navigate: true, mode: 'overview');

            foreach ($this->catalog->overviewPageSpots($room) as $spot) {
                $steps[] = $this->pageStep($room, $spot, mode: 'overview');
            }
        }

        return $steps;
    }

    /**
     * @param  array<string, mixed>  $room
     * @return list<array<string, mixed>>
     */
    public function pageDeep(array $room, ?string $path): array
    {
        $steps = [];
        $steps[] = $this->sidebarStep($room, navigate: false, mode: 'page_deep');

        $childLimit = max(0, (int) config('agent_tour.page_deep_child_limit', 6));
        $added = 0;

        foreach ($this->catalog->children((string) ($room['key'] ?? '')) as $child) {
            if ($added >= $childLimit) {
                break;
            }

            $steps[] = $this->sidebarChildStep($room, $child);
            $added++;
        }

        $spotLimit = max(1, (int) config('agent_tour.page_deep_spot_limit', 6));
        $spotCount = 0;
        $kind = $this->catalog->pageKind($path);

        foreach ($this->catalog->pageSpots($kind, $room) as $spot) {
            if ($spotCount >= $spotLimit) {
                break;
            }

            $steps[] = $this->pageStep($room, $spot, mode: 'page_deep');
            $spotCount++;
        }

        return $steps;
    }

    /**
     * @param  array<string, mixed>  $room
     * @return array<string, mixed>
     */
    protected function sidebarStep(array $room, bool $navigate, string $mode): array
    {
        $key = (string) ($room['key'] ?? 'unknown');

        return [
            'id' => $key.'.sidebar',
            'kind' => 'sidebar',
            'mode' => $mode,
            'room_key' => $key,
            'label' => (string) ($room['label'] ?? $key),
            'blurb' => (string) ($room['blurb'] ?? ''),
            'voice' => (string) ($room['voice'] ?? ''),
            'selector' => (string) ($room['selector'] ?? ''),
            'heading_selector' => $this->catalog->headingSelector(),
            'menu_names' => array_values(array_filter(
                (array) ($room['menu_names'] ?? []),
                fn ($name) => is_string($name) && $name !== '',
            )),
            'url' => (string) ($room['url'] ?? ''),
            'navigate' => $navigate,
            'spot_key' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $room
     * @param  array<string, mixed>  $child
     * @return array<string, mixed>
     */
    protected function sidebarChildStep(array $room, array $child): array
    {
        $key = (string) ($room['key'] ?? 'unknown');
        $label = (string) ($child['label'] ?? 'Submenu');

        return [
            'id' => $key.'.child.'.mb_strtolower($label),
            'kind' => 'sidebar',
            'mode' => 'page_deep',
            'room_key' => $key,
            'label' => $label,
            'blurb' => (string) ($child['blurb'] ?? ''),
            'voice' => (string) ($child['voice'] ?? ''),
            'selector' => (string) ($child['selector'] ?? ''),
            'heading_selector' => $this->catalog->headingSelector(),
            'menu_names' => array_values(array_filter(
                (array) ($child['menu_names'] ?? [$label]),
                fn ($name) => is_string($name) && $name !== '',
            )),
            'url' => (string) ($child['url'] ?? ''),
            'navigate' => false,
            'spot_key' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $room
     * @param  array<string, mixed>  $spot
     * @return array<string, mixed>
     */
    protected function pageStep(array $room, array $spot, string $mode): array
    {
        $key = (string) ($room['key'] ?? 'unknown');
        $spotKey = (string) ($spot['key'] ?? 'spot');

        return [
            'id' => $key.'.page.'.$spotKey,
            'kind' => 'page',
            'mode' => $mode,
            'room_key' => $key,
            'label' => (string) ($spot['label'] ?? $spotKey),
            'blurb' => (string) ($spot['blurb'] ?? ''),
            'voice' => (string) ($spot['voice'] ?? ''),
            'selector' => (string) ($spot['selector'] ?? ''),
            'heading_selector' => $this->catalog->headingSelector(),
            'menu_names' => array_values(array_filter(
                (array) ($spot['menu_names'] ?? []),
                fn ($name) => is_string($name) && $name !== '',
            )),
            'url' => (string) ($room['url'] ?? ''),
            'navigate' => false,
            'spot_key' => $spotKey,
        ];
    }
}
