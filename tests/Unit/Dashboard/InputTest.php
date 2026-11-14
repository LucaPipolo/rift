<?php

declare(strict_types=1);

use App\Dashboard\Input;

/**
 * Create a connected pair of streams, the first to read from and the second to write to.
 *
 * @return array{resource, resource} The streams.
 */
function streamPair(): array
{
    $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    assert(is_array($pair));

    return [$pair[0], $pair[1]];
}

describe('wait()', function (): void {
    it('returns the next line without its newline', function (): void {
        [$read, $write] = streamPair();
        fwrite($write, "refresh\r\n");

        expect(new Input($read)->wait(1000))->toBe('refresh');
    });

    it('returns an empty string when nothing comes in time', function (): void {
        [$read, $write] = streamPair();

        expect(new Input($read)->wait(10))->toBe('');

        fclose($write);
    });

    it('returns null once the stream has closed', function (): void {
        [$read, $write] = streamPair();
        fclose($write);

        expect(new Input($read)->wait(1000))->toBeNull();
    });
});
