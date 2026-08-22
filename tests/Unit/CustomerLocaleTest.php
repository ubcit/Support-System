<?php

use Modules\Communication\Support\CustomerLocale;

test('customer locale detects arabic script', function () {
    expect(CustomerLocale::detect('مرحبا أحتاج مساعدة'))->toBe('ar');
});

test('customer locale detects english latin letters', function () {
    expect(CustomerLocale::detect('Please fix the server'))->toBe('en');
});

test('customer locale treats mixed arabic and english as arabic', function () {
    expect(CustomerLocale::detect('Hello مرحبا'))->toBe('ar');
});

test('customer locale returns null for digits-only or empty text', function () {
    expect(CustomerLocale::detect('AB12CD'))->toBeNull();
    expect(CustomerLocale::detect('12345'))->toBeNull();
    expect(CustomerLocale::detect(''))->toBeNull();
    expect(CustomerLocale::detect(null))->toBeNull();
});

test('customer locale normalize falls back to english', function () {
    expect(CustomerLocale::normalize('ar'))->toBe('ar');
    expect(CustomerLocale::normalize('EN'))->toBe('en');
    expect(CustomerLocale::normalize('fr'))->toBe('en');
    expect(CustomerLocale::normalize(null))->toBe('en');
});
