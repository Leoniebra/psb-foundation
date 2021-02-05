<?php
declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace PSB\PsbFoundation\ViewHelpers;

use Closure;
use PSB\PsbFoundation\Service\GlobalVariableService;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Class GlobalVariablesViewHelper
 * @package PSB\PsbFoundation\ViewHelpers
 */
class GlobalVariablesViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('path', 'string', 'array path separated with dots', false);
    }

    public function render(): void
    {
        static::renderStatic([], $this->buildRenderChildrenClosure(), $this->renderingContext);
    }

    /**
     * @param array                     $arguments
     * @param Closure                   $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     *
     * @return mixed
     */
    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        if (empty($arguments['path'])) {
            $globalVariables = GlobalVariableService::getGlobalVariables();

            foreach ($globalVariables as $key => $value) {
                if (!$renderingContext->getVariableProvider()->exists($key)) {
                    $renderingContext->getVariableProvider()->add($key, $value);
                }
            }

            return null;
        }

        return GlobalVariableService::getGlobalVariables($arguments['path']);
    }
}
