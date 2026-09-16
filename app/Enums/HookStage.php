<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A point in the lifecycle where a project's hooks run.
 */
enum HookStage: string
{
    case BeforeUp = 'before_up';
    case AfterUp = 'after_up';
    case BeforeDown = 'before_down';
    case AfterDown = 'after_down';

    /**
     * Get the step label shown for a hook that has none of its own.
     *
     * @return string The label.
     */
    public function defaultLabel(): string
    {
        return match ($this) {
            self::BeforeUp => 'Preparing',
            self::AfterUp => 'Running project setup',
            self::BeforeDown => 'Preparing to stop',
            self::AfterDown => 'Cleaning up',
        };
    }
}
