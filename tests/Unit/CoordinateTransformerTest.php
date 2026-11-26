<?php

use App\Support\Geo\CoordinateTransformer;

test('UTM 37N coordinates convert to the expected Ethiopian positions', function () {
    $transformer = new CoordinateTransformer();

    $gondar = $transformer->toGeographic(328953.886081, 1384269.89518, 37);

    expect($gondar)->not->toBeNull()
        ->and($gondar['latitude'])->toEqualWithDelta(12.5173, 0.001)
        ->and($gondar['longitude'])->toEqualWithDelta(37.4259, 0.001);

    $moyale = $transformer->toGeographic(1197549.02857, 391268.159708, 37);

    expect($moyale['latitude'])->toEqualWithDelta(3.5186, 0.01)
        ->and($moyale['longitude'])->toEqualWithDelta(45.2679, 0.01);
});

test('a geographic position round-trips through the projection', function () {
    $transformer = new CoordinateTransformer();

    $projected = $transformer->fromGeographic(9.0333, 38.75, 37);
    $geographic = $transformer->toGeographic($projected['easting'], $projected['northing'], 37);

    expect($geographic['latitude'])->toEqualWithDelta(9.0333, 0.0001)
        ->and($geographic['longitude'])->toEqualWithDelta(38.75, 0.0001);
});

test('axis order is resolved when a projected file keeps lat/lon headers', function () {
    $transformer = new CoordinateTransformer();

    // The station list stores easting in the "Lat" column; the correct order
    // lands inside Ethiopia, the reversed order does not.
    [$easting, $northing] = $transformer->resolveAxes(328953.886081, 1384269.89518, 37);

    expect($easting)->toEqualWithDelta(328953.886081, 0.01)
        ->and($northing)->toEqualWithDelta(1384269.89518, 0.01);
});

test('degree values are recognised as geographic, not projected', function () {
    $transformer = new CoordinateTransformer();

    expect($transformer->looksProjected(9.0333, 38.75))->toBeFalse()
        ->and($transformer->looksProjected(328953.88, 1384269.89))->toBeTrue();
});

test('out of range positions are rejected instead of stored', function () {
    $transformer = new CoordinateTransformer();

    expect($transformer->toGeographic(-900000, 500000, 37))->toBeNull()
        ->and($transformer->toGeographic(500000, 20000000, 37))->toBeNull();
});
