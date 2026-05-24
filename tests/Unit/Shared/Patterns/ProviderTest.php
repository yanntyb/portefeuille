<?php

use App\Shared\Patterns\Provider;

// Test implementation
class TestProvider implements Provider
{
    private array $services = [];

    public function register(string $name, mixed $service): void
    {
        $this->services[$name] = $service;
    }

    public function get(string $name): mixed
    {
        if (!isset($this->services[$name])) {
            throw new RuntimeException("Service not found: {$name}");
        }

        return $this->services[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->services[$name]);
    }
}

it('implements provider interface', function () {
    $provider = new TestProvider();

    expect($provider)->toBeInstanceOf(Provider::class);
});

it('registers and retrieves services', function () {
    $provider = new TestProvider();
    $service = ['name' => 'test'];

    $provider->register('test', $service);

    expect($provider->get('test'))->toBe($service);
});

it('checks if service exists', function () {
    $provider = new TestProvider();

    $provider->register('test', 'value');

    expect($provider->has('test'))->toBeTrue()
        ->and($provider->has('missing'))->toBeFalse();
});

it('throws exception for missing service', function () {
    $provider = new TestProvider();

    expect(fn () => $provider->get('missing'))
        ->toThrow(RuntimeException::class);
});

it('registers multiple services', function () {
    $provider = new TestProvider();

    $provider->register('service1', 'value1');
    $provider->register('service2', 'value2');
    $provider->register('service3', ['data']);

    expect($provider->get('service1'))->toBe('value1')
        ->and($provider->get('service2'))->toBe('value2')
        ->and($provider->get('service3'))->toBe(['data']);
});
