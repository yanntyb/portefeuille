<?php

use App\Shared\Python\PythonResult;

it('builds from a full envelope and is ok', function () {
    $result = PythonResult::fromArray(['status' => 'ok', 'data' => ['x' => 1]]);

    expect($result->status)->toBe('ok')
        ->and($result->ok())->toBeTrue()
        ->and($result->data)->toBe(['x' => 1])
        ->and($result->error)->toBeNull();
});

it('exposes the error envelope', function () {
    $result = PythonResult::fromArray(['status' => 'error', 'error' => 'boom']);

    expect($result->ok())->toBeFalse()
        ->and($result->data)->toBeNull()
        ->and($result->error)->toBe('boom');
});

it('defaults status to error and data to null when absent', function () {
    $result = PythonResult::fromArray([]);

    expect($result->status)->toBe('error')
        ->and($result->ok())->toBeFalse()
        ->and($result->data)->toBeNull();
});

it('coerces non-array data to null', function () {
    $result = PythonResult::fromArray(['status' => 'ok', 'data' => 'not-an-array']);

    expect($result->data)->toBeNull();
});

it('is ok only for the ok status', function () {
    expect(PythonResult::fromArray(['status' => 'ok'])->ok())->toBeTrue()
        ->and(PythonResult::fromArray(['status' => 'pending'])->ok())->toBeFalse();
});
