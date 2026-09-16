<?php

declare(strict_types=1);

namespace App\Support;

use function Termwind\render;

/**
 * Renders every line rift prints to the terminal.
 */
final class Ui
{
    /**
     * Render the banner a run opens with.
     *
     * @param  string  $name  The project or router name.
     * @param  string  $subtitle  The text beside the name.
     * @param  string|null  $hint  The dimmed hint, if any.
     *
     * @return void
     */
    public static function banner(string $name, string $subtitle, ?string $hint = null): void
    {
        $tail = $hint === null
            ? ''
            : '<span class="text-gray ml-2">' . self::e($hint) . '</span>';

        render(<<<HTML
            <div class="mt-1 ml-2">
                <span class="font-bold">{$name}</span>
                <span class="text-gray ml-2">{$subtitle}</span>
                {$tail}
            </div>
        HTML);
    }

    /**
     * Render a finished step and the note explaining how it ended.
     *
     * @param  string  $icon  The status icon.
     * @param  string  $colour  The icon colour.
     * @param  string  $label  The step label.
     * @param  string  $note  The note on the right.
     *
     * @return void
     */
    public static function step(string $icon, string $colour, string $label, string $note = ''): void
    {
        $aside = $note === ''
            ? ''
            : '<span class="text-gray">' . self::e($note) . '</span>';

        render(<<<HTML
            <div class="flex mx-2">
                <span class="text-{$colour}">{$icon}</span>
                <span class="ml-2">{$label}</span>
                <span class="flex-1 mx-1"></span>
                {$aside}
            </div>
        HTML);
    }

    /**
     * Render a labelled fact rather than the outcome of a step.
     *
     * @param  string  $label  The fact's label.
     * @param  string  $value  The fact's value.
     *
     * @return void
     */
    public static function fact(string $label, string $value): void
    {
        render(<<<HTML
            <div class="flex mx-2">
                <span class="text-green">✓</span>
                <span class="ml-2">{$label}</span>
                <span class="flex-1 mx-1"></span>
                <span class="text-gray">{$value}</span>
            </div>
        HTML);
    }

    /**
     * Render the top edge of a panel, titled on the left and annotated on the right.
     *
     * @param  string  $title  The panel title.
     * @param  string  $note  The note on the right.
     *
     * @return void
     */
    public static function rule(string $title, string $note = ''): void
    {
        $right = $note === ''
            ? ''
            : '<span class="text-gray mx-1">' . self::e($note) . '</span>';

        render(<<<HTML
            <div class="flex ml-2 mt-1">
                <span class="text-gray">┌─</span>
                <span class="font-bold mx-1">{$title}</span>
                <span class="flex-1 content-repeat-[─] text-gray"></span>
                {$right}
                <span class="text-gray">─┐</span>
            </div>
        HTML);
    }

    /**
     * Render the bottom edge of a panel with its summary.
     *
     * @param  string  $note  The summary on the left.
     *
     * @return void
     */
    public static function endRule(string $note = ''): void
    {
        $left = $note === ''
            ? ''
            : '<span class="text-gray mx-1">' . self::e($note) . '</span>';

        render(<<<HTML
            <div class="flex mx-2">
                <span class="text-gray">└─</span>
                {$left}
                <span class="flex-1 content-repeat-[─] text-gray"></span>
                <span class="text-gray">─┘</span>
            </div>
        HTML);
    }

    /**
     * Render the heading above a group of rows.
     *
     * @param  string  $text  The heading text.
     *
     * @return void
     */
    public static function heading(string $text): void
    {
        render(<<<HTML
            <div class="ml-3 mt-1 text-yellow font-bold uppercase">{$text}</div>
        HTML);
    }

    /**
     * Render a listing row, its label led to its value by dots.
     *
     * @param  string  $marker  A one-character marker, or empty.
     * @param  string  $left  The row label.
     * @param  string  $right  The row value.
     * @param  string  $colour  The marker colour.
     * @param  bool  $muted  Whether to dim the label.
     *
     * @return void
     */
    public static function row(
        string $marker,
        string $left,
        string $right,
        string $colour = 'green',
        bool $muted = false,
    ): void {
        $text = $muted ? 'text-gray' : '';
        // Both branches are one column wide so marked and unmarked rows line up.
        $glyph = $marker === '' ? ' ' : $marker;
        $lead = "<span class=\"text-{$colour}\">{$glyph}</span>";

        render(<<<HTML
            <div class="flex mx-3">
                {$lead}
                <span class="ml-1 {$text}">{$left}</span>
                <span class="flex-1 content-repeat-[.] text-gray mx-1"></span>
                <span class="text-gray">{$right}</span>
            </div>
        HTML);
    }

    /**
     * Render a checklist item.
     *
     * @param  string  $icon  The status icon.
     * @param  string  $colour  The icon colour.
     * @param  string  $text  The item text.
     *
     * @return void
     */
    public static function item(string $icon, string $colour, string $text): void
    {
        render(<<<HTML
            <div class="flex ml-3">
                <span class="text-{$colour}">{$icon}</span>
                <span class="ml-1">{$text}</span>
            </div>
        HTML);
    }

    /**
     * Render the advice under a failed check.
     *
     * @param  string  $text  The advice, as markup.
     *
     * @return void
     */
    public static function hint(string $text): void
    {
        render('<div class="ml-5 text-gray">' . $text . '</div>');
    }

    /**
     * Render a bordered notice for something the user has to act on.
     *
     * @param  string  $title  The notice title.
     * @param  list<string>  $lines  Markup; pass untrusted values through e().
     * @param  string  $colour  The border colour.
     *
     * @return void
     */
    public static function notice(string $title, array $lines, string $colour = 'yellow'): void
    {
        $body = '';

        foreach ($lines as $line) {
            $body .= "<div class=\"flex ml-2\"><span class=\"text-{$colour}\">│</span>"
                . '<span class="ml-2">' . ($line === '' ? ' ' : $line) . '</span></div>';
        }

        render(<<<HTML
            <div class="mt-1">
                <div class="flex ml-2">
                    <span class="text-{$colour}">┌─</span>
                    <span class="text-{$colour} ml-1">{$title}</span>
                </div>
                {$body}
                <div class="ml-2 text-{$colour}">└</div>
            </div>
        HTML);
    }

    /**
     * Render the closing line of a successful run.
     *
     * @param  string  $message  The closing message.
     *
     * @return void
     */
    public static function done(string $message): void
    {
        render(<<<HTML
            <div class="flex ml-2 mt-1">
                <span class="text-green">✓</span>
                <span class="ml-2 font-bold">{$message}</span>
            </div>
        HTML);
    }

    /**
     * Render the closing line of a failed run.
     *
     * @param  string  $message  The error message.
     *
     * @return void
     */
    public static function error(string $message): void
    {
        render(<<<HTML
            <div class="flex ml-2 mt-1">
                <span class="text-red">✗</span>
                <span class="ml-2">{$message}</span>
            </div>
        HTML);
    }

    /**
     * Render a line of secondary text.
     *
     * @param  string  $text  The text, as markup.
     *
     * @return void
     */
    public static function muted(string $text): void
    {
        render('<div class="ml-2 text-gray">' . $text . '</div>');
    }

    /**
     * Render an empty line.
     *
     * @return void
     */
    public static function blank(): void
    {
        render('<div></div>');
    }

    /**
     * Escape a value so it is never read as markup.
     *
     * @param  string  $value  The raw value.
     *
     * @return string The escaped value.
     */
    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
