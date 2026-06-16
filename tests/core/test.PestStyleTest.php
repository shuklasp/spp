<?php

test('addition works with functional API', function () {
    expect(1 + 1)->toBe(2);
});

it('can access $this via closure binding', function () {
    $this->assertTrue(true);
});

test('subtraction works with functional API', function () {
    expect(5 - 2)->toBe(3);
});
