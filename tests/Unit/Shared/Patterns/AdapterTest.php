<?php

use App\Shared\Patterns\Adapter;

// Test data source
class ArrayDataSource
{
    public function __construct(
        private array $data,
    ) {
    }

    public function getData(): array
    {
        return $this->data;
    }
}

// Test adapter implementation
class ArrayToJsonAdapter extends Adapter
{
    private ArrayDataSource $dataSource;

    public function __construct(ArrayDataSource $dataSource)
    {
        $this->dataSource = $dataSource;
    }

    public function adapt(): string
    {
        return json_encode($this->dataSource->getData());
    }
}

// Another test adapter
class ArrayCountAdapter extends Adapter
{
    private ArrayDataSource $dataSource;

    public function __construct(ArrayDataSource $dataSource)
    {
        $this->dataSource = $dataSource;
    }

    public function adapt(): int
    {
        return count($this->dataSource->getData());
    }
}

it('adapts array data source to json', function () {
    $source = new ArrayDataSource(['name' => 'test', 'value' => 42]);
    $adapter = new ArrayToJsonAdapter($source);

    $result = $adapter->adapt();

    expect($result)->toBe('{"name":"test","value":42}');
});

it('adapts array data source to count', function () {
    $source = new ArrayDataSource(['a', 'b', 'c']);
    $adapter = new ArrayCountAdapter($source);

    $result = $adapter->adapt();

    expect($result)->toBe(3);
});

it('is instance of adapter', function () {
    $source = new ArrayDataSource(['test']);
    $adapter = new ArrayToJsonAdapter($source);

    expect($adapter)->toBeInstanceOf(Adapter::class);
});

it('adapts different data sources independently', function () {
    $source1 = new ArrayDataSource(['a', 'b']);
    $source2 = new ArrayDataSource(['x', 'y', 'z']);

    $adapter1 = new ArrayCountAdapter($source1);
    $adapter2 = new ArrayCountAdapter($source2);

    expect($adapter1->adapt())->toBe(2)
        ->and($adapter2->adapt())->toBe(3);
});
