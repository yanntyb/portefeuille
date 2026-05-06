<?php

use App\Domains\Analytics\Data\Simulation\SimulationValue;
use App\Domains\Analytics\Data\Simulation\ValueFormat;

it('parses euro values', function (): void {
    $value = SimulationValue::parse('3 862,50 €');

    expect($value->numeric)->toBe(3862.50)
        ->and($value->format)->toBe(ValueFormat::Euro);
});

it('parses percent values', function (): void {
    $value = SimulationValue::parse('42,00 %');

    expect($value->numeric)->toBe(0.42)
        ->and($value->format)->toBe(ValueFormat::Percent);
});

it('parses plain integers', function (): void {
    $value = SimulationValue::parse('218');

    expect($value->numeric)->toBe(218.0)
        ->and($value->format)->toBe(ValueFormat::Plain);
});

it('parses empty string as null', function (): void {
    $value = SimulationValue::parse('');

    expect($value->numeric)->toBeNull()
        ->and($value->format)->toBe(ValueFormat::Plain);
});

it('parses dash as null', function (): void {
    $value = SimulationValue::parse('—');

    expect($value->numeric)->toBeNull()
        ->and($value->format)->toBe(ValueFormat::Plain);
});

it('parses plain decimal', function (): void {
    $value = SimulationValue::parse('0,70');

    expect($value->numeric)->toBe(0.70)
        ->and($value->format)->toBe(ValueFormat::Plain);
});

it('formats euro values', function (): void {
    expect(SimulationValue::euro(3862.50)->formatted())->toBe('3 862,50 €');
});

it('formats percent values', function (): void {
    expect(SimulationValue::percent(0.42)->formatted())->toBe('42,00 %');
});

it('formats plain integers', function (): void {
    expect(SimulationValue::plain(218)->formatted())->toBe('218');
});

it('formats plain decimals', function (): void {
    expect(SimulationValue::plain(0.70)->formatted())->toBe('0,70');
});

it('formats null as dash', function (): void {
    expect((new SimulationValue(null, ValueFormat::Euro))->formatted())->toBe('—');
});

it('roundtrips euro through parse and format', function (): void {
    $original = '3 862,50 €';
    $parsed = SimulationValue::parse($original);

    expect($parsed->formatted())->toBe($original);
});

it('roundtrips percent through parse and format', function (): void {
    $original = '42,00 %';
    $parsed = SimulationValue::parse($original);

    expect($parsed->formatted())->toBe($original);
});
