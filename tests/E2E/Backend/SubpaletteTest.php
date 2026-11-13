<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\E2eTests\Backend;

use Contao\E2eTesting\Browser\BackendBrowser;
use Contao\E2eTests\AbstractContaoMonorepoE2ETestCase;

class SubpaletteTest extends AbstractContaoMonorepoE2ETestCase
{
    use BackendTestTrait;

    public function testTogglesSubpalette(): void
    {
        $backend = $this->login();
        $page = $backend->page();
        $checkbox = $page->locator('input[name="protected"][type="checkbox"]');
        $subpalette = $page->locator('#sub_protected');

        $this->visitPage($backend);

        $this->assertFalse($checkbox->isChecked());
        $this->assertSame(0, $subpalette->count());

        // Loads the subpalette and stores the state
        $backend->checkAndWaitForAjax('protected');
        $backend->waitFor('#sub_protected #ctrl_groups');

        $this->visitPage($backend);

        $this->assertTrue($checkbox->isChecked());
        $this->assertTrue($subpalette->isVisible());

        // Toggles the loaded subpalette without loading it again
        $this->waitForRequest($backend, static fn () => $checkbox->uncheck());
        $this->assertTrue($subpalette->isHidden());

        $this->waitForRequest($backend, static fn () => $checkbox->check());
        $this->assertTrue($subpalette->isVisible());
        $this->assertSame(1, $subpalette->count());

        $this->waitForRequest($backend, static fn () => $checkbox->uncheck());

        $this->visitPage($backend);

        $this->assertFalse($checkbox->isChecked());
        $this->assertSame(0, $subpalette->count());
    }

    private function visitPage(BackendBrowser $backend): void
    {
        $fixtures = self::managedEdition()->database()->fixtures();
        $backend->visit($fixtures->interpolate('/contao?do=page&act=edit&id={page_main_home}'));

        $legend = $backend->page()->locator('fieldset.collapsed:has(input[name="protected"]) legend button');

        if ($legend->count()) {
            $legend->click();
        }
    }

    /**
     * @param callable(): mixed $action
     */
    private function waitForRequest(BackendBrowser $backend, callable $action): void
    {
        $backend->page()->evaluate(
            <<<'JS'
                (() => {
                    const original = window.fetch;

                    window.fetch = async (...args) => {
                        try {
                            return await original(...args);
                        } finally {
                            window.fetch = original;
                            window.subpaletteRequestDone = true;
                        }
                    };

                    window.subpaletteRequestDone = false;

                    return null;
                })()
                JS,
        );

        $action();

        $backend->page()->waitForFunction('() => window.subpaletteRequestDone === true');
    }
}
