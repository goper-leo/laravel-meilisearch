<?php

namespace Eelcol\LaravelMeilisearch\Connector;

use ArrayAccess;
use Countable;
use Eelcol\LaravelMeilisearch\Connector\Traits\HandlesErrors;
use Eelcol\LaravelMeilisearch\Exceptions\IndexAlreadyExists;
use Eelcol\LaravelMeilisearch\Exceptions\IndexNotFound;
use Eelcol\LaravelMeilisearch\Exceptions\MissingDocumentId;
use Illuminate\Http\Client\Response;
use IteratorAggregate;
use ReturnTypeWillChange;

class MeilisearchResponse implements ArrayAccess, IteratorAggregate, Countable
{
    use HandlesErrors;

    protected array $data;

    /**
     * @throws MissingDocumentId
     * @throws IndexAlreadyExists
     * @throws IndexNotFound
     */
    public function __construct(
        protected Response $response
    ) {
        $this->data = $this->response->json();

        if (array_key_exists('results', $this->data)) {
            $this->data = $this->data['results'];
        }

        if (array_key_exists('facetHits', $this->data)) {
            $this->data = $this->data['facetHits'];
        }

        if (array_key_exists('message', $this->data) && array_key_exists('code', $this->data)) {
            $this->checkForErrors();
        }
    }

    public function getData()
    {
        return $this->data;
    }

    /**
     * Get the current page number (1-indexed)
     */
    public function getCurrentPage(): ?int
    {
        $page = $this->response->json('page');
        if (!is_null($page)) {
            return $page;
        }

        $offset = $this->response->json('offset');
        $limit = $this->response->json('limit');
        if (!is_null($offset) && !is_null($limit)) {
            return ceil($offset / $limit) + 1;
        }

        return null;
    }

    public function getTotalPages(): ?int
    {
        $totalPages = $this->response->json('totalPages');
        if (!is_null($totalPages)) {
            return $totalPages;
        }

        $limit = $this->getHitsPerPage();
        if (!is_null($limit)) {
            $totalHits = $this->getTotalHits();
            if (!is_null($totalHits)) {
                return ceil($totalHits / $limit);
            }
        }

        return null;
    }

    public function getTotalHits(): ?int
    {
        /**
         * A paginated result will have the key 'totalHits' or 'total'
         * A non-paginated result will have 'estimatedTotalHits'
         */
        return $this->response->json('totalHits') ?? $this->response->json('estimatedTotalHits') ?? $this->response->json('total');
    }

    public function getHitsPerPage(): ?int
    {
        return $this->response->json('hitsPerPage') ?? $this->response->json('limit');
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
    }

    public function offsetSet($offset, $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]) || \array_key_exists($offset, $this->data);
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if (isset($this->data[$offset])) {
            return $this->data[$offset];
        }

        return null;
    }

    public function count(): int
    {
        return count($this->data);
    }
}