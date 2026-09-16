<?php

declare(strict_types=1);

use App\Support\Ui;
use Symfony\Component\Console\Output\BufferedOutput;

use function Termwind\renderUsing;

beforeEach(function (): void {
    $this->output = new BufferedOutput();
    renderUsing($this->output);
});

afterEach(function (): void {
    renderUsing(null);
});

describe('banner()', function (): void {
    it('shows the name and subtitle', function (): void {
        Ui::banner('myapp', 'dev environment');

        expect($this->output->fetch())->toContain('myapp  dev environment');
    });

    it('shows the hint beside the subtitle', function (): void {
        Ui::banner('myapp', 'dev environment', '-v for details');

        expect($this->output->fetch())->toContain('dev environment  -v for details');
    });

    it('escapes markup in the hint', function (): void {
        Ui::banner('myapp', 'dev environment', '<b>bold</b>');

        expect($this->output->fetch())->toContain('<b>bold</b>');
    });
});

describe('step()', function (): void {
    it('shows the icon and label', function (): void {
        Ui::step('✓', 'green', 'Starting containers');

        expect($this->output->fetch())->toContain('✓  Starting containers');
    });

    it('shows the note on the right', function (): void {
        Ui::step('–', 'gray', 'Starting router', 'already running');

        expect($this->output->fetch())->toContain('already running');
    });
});

describe('fact()', function (): void {
    it('shows the label and value', function (): void {
        Ui::fact('Project', 'myapp (myapp.test)');

        expect($this->output->fetch())->toMatch('/Project\s+myapp \(myapp\.test\)/');
    });
});

describe('rule()', function (): void {
    it('shows the title', function (): void {
        Ui::rule('rift.yaml');

        expect($this->output->fetch())->toContain('┌─ rift.yaml ─');
    });

    it('shows the note on the right', function (): void {
        Ui::rule('rift.yaml', 'written');

        expect($this->output->fetch())->toMatch('/─ written ─┐/');
    });
});

describe('endRule()', function (): void {
    it('closes the panel without a note', function (): void {
        Ui::endRule();

        expect($this->output->fetch())->toContain('└──');
    });

    it('shows the note on the left', function (): void {
        Ui::endRule('rift setup to bring it up');

        expect($this->output->fetch())->toContain('└─ rift setup to bring it up ─');
    });
});

describe('heading()', function (): void {
    it('shows the text in upper case', function (): void {
        Ui::heading('will serve');

        expect($this->output->fetch())->toContain('WILL SERVE');
    });
});

describe('row()', function (): void {
    it('leads the label to the value with dots', function (): void {
        Ui::row('✓', 'myapp.test', 'this checkout');

        expect($this->output->fetch())->toMatch('/✓ myapp\.test \.+ this checkout/');
    });

    it('lines an unmarked row up with marked ones', function (): void {
        Ui::row('', 'APP_PORT', '80', muted: true);

        expect($this->output->fetch())->toContain('    APP_PORT .');
    });
});

describe('item()', function (): void {
    it('shows the icon and text', function (): void {
        Ui::item('!', 'yellow', 'No compose file found here.');

        expect($this->output->fetch())->toContain('! No compose file found here.');
    });
});

describe('hint()', function (): void {
    it('shows the advice', function (): void {
        Ui::hint('networks: [default, router]');

        expect($this->output->fetch())->toContain('networks: [default, router]');
    });
});

describe('notice()', function (): void {
    it('shows the title', function (): void {
        Ui::notice('docker', ['Start Docker Desktop.']);

        expect($this->output->fetch())->toContain('┌─ docker');
    });

    it('borders every line', function (): void {
        Ui::notice('docker', ['Start Docker Desktop.']);

        expect($this->output->fetch())->toContain('│  Start Docker Desktop.');
    });

    it('keeps an empty line as a bordered blank line', function (): void {
        Ui::notice('docker', ['first', '', 'second']);

        expect(substr_count($this->output->fetch(), '│'))->toBe(3);
    });
});

describe('done()', function (): void {
    it('shows the message after a tick', function (): void {
        Ui::done('stopped');

        expect($this->output->fetch())->toContain('✓  stopped');
    });
});

describe('error()', function (): void {
    it('shows the message after a cross', function (): void {
        Ui::error('Not inside a git checkout.');

        expect($this->output->fetch())->toContain('✗  Not inside a git checkout.');
    });
});

describe('muted()', function (): void {
    it('shows the text', function (): void {
        Ui::muted('Log: /tmp/rift-up.log');

        expect($this->output->fetch())->toContain('Log: /tmp/rift-up.log');
    });
});

describe('blank()', function (): void {
    it('renders an empty line', function (): void {
        Ui::blank();

        expect(trim($this->output->fetch()))->toBe('');
    });
});

describe('e()', function (): void {
    it('escapes markup', function (): void {
        expect(Ui::e('<b class="x">'))->toBe('&lt;b class=&quot;x&quot;&gt;');
    });

    it('escapes single quotes', function (): void {
        expect(Ui::e("it's"))->toBe('it&#039;s');
    });
});
