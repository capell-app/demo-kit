<?php

declare(strict_types=1);

namespace Capell\DemoKit\Livewire;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class ResourcesLibrary extends Component
{
    use WithPagination;

    /** @var list<array<string, string>> */
    public array $items = [];

    /** @var list<string> */
    public array $filters = [];

    /** @var array<string, string> */
    public array $cta = [];

    #[Url(as: 'topic', except: '')]
    public string $filter = '';

    public int $perPage = 3;

    private string $pageName = 'page';

    /**
     * @param  list<array<string, string>>  $items
     * @param  list<string>  $filters
     * @param  array<string, string>  $cta
     */
    public function mount(array $items = [], array $filters = [], array $cta = []): void
    {
        $this->items = $items;
        $this->filters = $filters;
        $this->cta = $cta;
        $this->filter = $this->normalizedFilter($this->filter);
    }

    public function selectFilter(string $filter): void
    {
        $this->filter = $this->normalizedFilter($filter);
        $this->resetPage();
    }

    public function render(): View
    {
        return view('capell-demo-kit::livewire.resources-library', [
            'resources' => $this->resources(),
            'activeFilter' => $this->activeFilter(),
        ]);
    }

    /** @return LengthAwarePaginator<int, array<string, string>> */
    private function resources(): LengthAwarePaginator
    {
        $items = $this->filteredItems();
        $page = $this->getPage();

        return new Paginator(
            items: $items->forPage($page, $this->perPage)->values(),
            total: $items->count(),
            perPage: $this->perPage,
            currentPage: $page,
            options: [
                'path' => request()->url(),
                'pageName' => $this->pageName,
            ],
        );
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function filteredItems(): Collection
    {
        $activeFilter = $this->activeFilter();

        return collect($this->items)
            ->filter(fn (array $item): bool => $activeFilter === 'All resources' || ($item['label'] ?? '') === $activeFilter)
            ->values();
    }

    private function activeFilter(): string
    {
        return $this->normalizedFilter($this->filter);
    }

    private function normalizedFilter(string $filter): string
    {
        if ($filter === '' || $filter === 'All resources') {
            return 'All resources';
        }

        return in_array($filter, $this->filters, true) ? $filter : 'All resources';
    }
}
