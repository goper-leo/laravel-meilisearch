<?php

namespace Eelcol\LaravelMeilisearch\Tests\Feature\Ms14;

use Eelcol\LaravelMeilisearch\Connector\Facades\Meilisearch;
use Eelcol\LaravelMeilisearch\Connector\Facades\MeilisearchQuery;
use Eelcol\LaravelMeilisearch\Connector\MeilisearchConnector;
use Eelcol\LaravelMeilisearch\Tests\TestCase;

class MetadataTest extends TestCase
{
    protected function fillMeilisearch(): void
    {
        // create connection
        $connector = new MeilisearchConnector([
            'host' => 'http://meilisearch142:7700',
            'key' => 'test'
        ]);

        app()->instance(
            'meilisearch',
            $connector
        );

        // create index
        if (Meilisearch::indexExists('products')) {
            Meilisearch::deleteIndex("products")->wait();
        }

        Meilisearch::createIndex("products", "id")->wait();

        Meilisearch::syncFilterableAttributes("products", ['categories','color','size','brand']);
        Meilisearch::syncSearchableAttributes("products", ['categories','color','size','brand']);
        Meilisearch::syncSortableAttributes("products", ['categories','color','size','brand']);
        Meilisearch::setMaxTotalHits("products", 10000);
        Meilisearch::setMaxValuesPerFacet("products", 1000);

        // now fill with data
        $categories = ['phones', 'tablets', 'other'];
        $colors = ['black', 'blue', 'red'];
        $sizes = ['s','m','l'];
        $brands = ['Apple','Samsung'];

        $id = 1;
        $documents = [];
        foreach ($brands as $brand) {
            foreach ($sizes as $size) {
                foreach ($colors as $color) {
                    foreach ($categories as $category) {
                        $documents[] = [
                            'id' => $id,
                            'title' => $brand . " " . $category . " " . $color . " " . $size,
                            'categories' => [$category],
                            'color' => $color,
                            'size' => $size,
                            'brand' => $brand
                        ];
                        $id++;
                    }
                }
            }
        }

        Meilisearch::addDocuments("products", $documents)->wait();
    }

    /**
     * @return void
     * @throws \Eelcol\LaravelMeilisearch\Exceptions\IndexNotSupplied
     * @throws \Eelcol\LaravelMeilisearch\Exceptions\InvalidWhereBoolean
     * When you want to keep the facet in metadata, the same number of records must be returned.
     */
    public function testFacetMetadataQuery()
    {
        $this->fillMeilisearch();

        $result1 = MeilisearchQuery::index('products')
            ->whereIn('categories', ['phones','tablets'])
            ->where('color', '=', 'red')
            ->setFacets(['categories'])
            ->get();

        $result2 = MeilisearchQuery::index('products')
            ->whereIn('categories', ['phones','tablets'])
            ->keepFacetsInMetadata(function ($q) {
                $q->where('color', '=', 'red');
            })
            ->setFacets(['categories'])
            ->get();

        $this->assertEquals($result1->count(), $result2->count());
    }
}