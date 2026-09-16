<?php

declare(strict_types=1);

use App\Support\Outcome;

describe('done()', function (): void {
    it('returns a green tick with the note', function (): void {
        $outcome = Outcome::done('written');

        expect([$outcome->icon, $outcome->colour, $outcome->note])->toBe(['✓', 'green', 'written']);
    });

    it('returns an outcome that does not stop the run', function (): void {
        expect(Outcome::done()->fatal)->toBeFalse();
    });

    it('returns an empty note when none is given', function (): void {
        expect(Outcome::done()->note)->toBe('');
    });
});

describe('skipped()', function (): void {
    it('returns a grey dash with the note', function (): void {
        $outcome = Outcome::skipped('already there');

        expect([$outcome->icon, $outcome->colour, $outcome->note])->toBe(['–', 'gray', 'already there']);
    });

    it('returns an outcome that does not stop the run', function (): void {
        expect(Outcome::skipped()->fatal)->toBeFalse();
    });
});

describe('failed()', function (): void {
    it('returns a red cross with the note', function (): void {
        $outcome = Outcome::failed('port taken');

        expect([$outcome->icon, $outcome->colour, $outcome->note])->toBe(['✗', 'red', 'port taken']);
    });

    it('returns an outcome that stops the run', function (): void {
        expect(Outcome::failed()->fatal)->toBeTrue();
    });
});

describe('changed()', function (): void {
    it('returns a done outcome with the done note when something changed', function (): void {
        expect(Outcome::changed(true, 'written', 'unchanged'))->toEqual(Outcome::done('written'));
    });

    it('returns a skipped outcome with the skipped note when nothing changed', function (): void {
        expect(Outcome::changed(false, 'written', 'unchanged'))->toEqual(Outcome::skipped('unchanged'));
    });
});

describe('succeeded()', function (): void {
    it('returns a done outcome without a note when the work succeeded', function (): void {
        expect(Outcome::succeeded(true, 'broke'))->toEqual(Outcome::done());
    });

    it('returns a failed outcome with the failure note when the work failed', function (): void {
        expect(Outcome::succeeded(false, 'broke'))->toEqual(Outcome::failed('broke'));
    });
});
