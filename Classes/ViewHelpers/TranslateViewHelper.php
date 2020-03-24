<?php
declare(strict_types=1);
namespace PSB\PsbFoundation\ViewHelpers;

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

use PSB\PsbFoundation\Utility\LocalizationUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Extbase\Object\Exception;

/**
 * Class TranslateViewHelper
 *
 * Overwrites the core ViewHelper in order to use \PSB\PsbFoundation\Utility\LocalizationUtility which is able to log
 * missing translations.
 *
 * @package PSB\PsbFoundation\ViewHelpers
 */
class TranslateViewHelper extends \TYPO3\CMS\Fluid\ViewHelpers\TranslateViewHelper
{
    /**
     * Wrapper call to static LocalizationUtility
     *
     * @param string   $id                      Translation Key
     * @param string   $extensionName           UpperCamelCased extension key (for example BlogExample)
     * @param array    $arguments               Arguments to be replaced in the resulting string
     * @param string   $languageKey             Language key to use for this translation
     * @param string[] $alternativeLanguageKeys Alternative language keys if no translation does exist
     *
     * @return string|null
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    protected static function translate(
        $id,
        $extensionName,
        $arguments,
        $languageKey,
        $alternativeLanguageKeys
    ): ?string {
        return LocalizationUtility::translate($id, $extensionName, $arguments, $languageKey, $alternativeLanguageKeys);
    }
}
