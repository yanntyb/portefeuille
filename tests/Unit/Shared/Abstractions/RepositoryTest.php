<?php

use App\Shared\Abstractions\Repository;

class SimpleRepository extends Repository
{
    public function find(mixed $id): mixed
    {
        return null;
    }

    public function all(): array
    {
        return [];
    }
}

it('can extend Repository abstract class', function () {
    $repository = new SimpleRepository();

    expect($repository)->toBeInstanceOf(Repository::class);
});

it('find method returns null', function () {
    $repository = new SimpleRepository();

    expect($repository->find(1))->toBeNull();
});

it('all method returns empty array', function () {
    $repository = new SimpleRepository();

    expect($repository->all())->toEqual([]);
});
