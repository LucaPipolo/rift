<?php

declare(strict_types=1);

use App\Enums\HookStage;

describe('defaultLabel()', function (): void {
    it('returns the step label for the stage', function (HookStage $stage, string $label): void {
        expect($stage->defaultLabel())->toBe($label);
    })->with([
        'before up' => [HookStage::BeforeUp, 'Preparing'],
        'after up' => [HookStage::AfterUp, 'Running project setup'],
        'before down' => [HookStage::BeforeDown, 'Preparing to stop'],
        'after down' => [HookStage::AfterDown, 'Cleaning up'],
    ]);
});
