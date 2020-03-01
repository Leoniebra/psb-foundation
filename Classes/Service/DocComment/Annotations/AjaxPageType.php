<?php
declare(strict_types=1);
namespace PSB\PsbFoundation\Service\DocComment\Annotations;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2020 Daniel Ablass <dn@phantasie-schmiede.de>, PSbits
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use PSB\PsbFoundation\Utility\TypoScript\PageObjectConfiguration;
use PSB\PsbFoundation\Utility\ValidationUtility;

/**
 * Class AjaxPageType
 * @Annotation
 * @package PSB\PsbFoundation\Service\DocComment\Annotations
 */
class AjaxPageType extends AbstractAnnotation
{
    /**
     * @var bool
     */
    protected bool $cacheable = false;

    /**
     * Has to be a value of PageObjectConfiguration::CONTENT_TYPES.
     * @var string
     */
    protected string $contentType;

    /**
     * @var int
     */
    protected int $typeNum;

    /**
     * @return bool
     */
    public function isCacheable(): bool
    {
        return $this->cacheable;
    }

    /**
     * @param bool $cacheable
     */
    public function setCacheable(bool $cacheable): void
    {
        $this->cacheable = $cacheable;
    }

    /**
     * @return string
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * @param string $contentType
     */
    public function setContentType(string $contentType): void
    {
        ValidationUtility::checkValueAgainstConstant(PageObjectConfiguration::CONTENT_TYPES, $contentType);
        $this->contentType = $contentType;
    }

    /**
     * @return int
     */
    public function getTypeNum(): int
    {
        return $this->typeNum;
    }

    /**
     * @param int $typeNum
     */
    public function setTypeNum(int $typeNum): void
    {
        $this->typeNum = $typeNum;
    }
}
