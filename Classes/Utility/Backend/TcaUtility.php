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

namespace PSB\PsbFoundation\Utility\Backend;

use Exception;
use InvalidArgumentException;
use PSB\PsbFoundation\Service\Configuration\TcaService;
use PSB\PsbFoundation\Utility\ExtensionInformationUtility;
use PSB\PsbFoundation\Utility\ObjectUtility;
use PSB\PsbFoundation\Utility\StringUtility;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class TcaUtility
 * @package PSB\PsbFoundation\Utility\Backend
 */
class TcaUtility
{
    /**
     * @var array
     */
    protected static array $classTableMapping = [];

    /**
     * This function will be executed when the core builds the TCA, but as it does not return an array there will be no
     * entry for the required file. Instead this function expands the TCA on its own by scanning through the domain
     * models of all registered extensions (extensions which provide an ExtensionInformation class, see
     * \PSB\PsbFoundation\Data\AbstractExtensionInformation).
     * Transient domain models (those without a corresponding table in the database) will be skipped.
     *
     * @param bool $overrideMode If set to false, the configuration of all original domain models (not extending other
     *                           domain models) is added to the TCA.
     *                           If set to true, the configuration of all extending domain models is added to the TCA.
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public static function buildTca(bool $overrideMode): void
    {
        if (empty(self::$classTableMapping)) {
            self::buildClassesTableMapping();
        }

        if ($overrideMode) {
            $key = 'tcaOverrides';
        } else {
            $key = 'tca';
        }

        if (isset(self::$classTableMapping[$key])) {
            foreach (self::$classTableMapping[$key] as $fullQualifiedClassName => $tableName) {
                $tcaConfiguration = ObjectUtility::get(TcaService::class,
                    $fullQualifiedClassName)->buildFromDocComment()->getConfiguration();

                if (is_array($tcaConfiguration)) {
                    $GLOBALS['TCA'][$tableName] = $tcaConfiguration;
                }
            }
        }
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    private static function buildClassesTableMapping(): void
    {
        self::$classTableMapping = [];
        $allExtensionInformation = ExtensionInformationUtility::getExtensionInformation();

        foreach ($allExtensionInformation as $extensionInformation) {
            try {
                $finder = Finder::create()
                    ->files()
                    ->in(ExtensionManagementUtility::extPath($extensionInformation->getExtensionKey()) . 'Classes/Domain/Model')
                    ->name('*.php');
            } catch (InvalidArgumentException $e) {
                // No such directory in this extension
                continue;
            }

            /** @var SplFileInfo $fileInfo */
            foreach ($finder as $fileInfo) {
                $classNameComponents = array_merge(
                    [
                        $extensionInformation->getVendorName(),
                        $extensionInformation->getExtensionName(),
                        'Domain\Model',
                    ],
                    explode('/', substr($fileInfo->getRelativePathname(), 0, -4))
                );

                $fullQualifiedClassName = implode('\\', $classNameComponents);
                $reflectionClass = GeneralUtility::makeInstance(ReflectionClass::class, $fullQualifiedClassName);

                if ($reflectionClass->isAbstract() || $reflectionClass->isInterface()) {
                    continue;
                }

                $tableName = ExtensionInformationUtility::convertClassNameToTableName($fullQualifiedClassName);

                $tableExists = ObjectUtility::get(ConnectionPool::class)
                    ->getConnectionForTable($tableName)
                    ->getSchemaManager()
                    ->tablesExist([$tableName]);

                if (!$tableExists) {
                    // This class seems to be no persistent domain model and will be skipped as a corresponding table is missing.
                    continue;
                }

                if (StringUtility::beginsWith($tableName, 'tx_' . mb_strtolower($extensionInformation->getExtensionName()))) {
                    self::$classTableMapping['tca'][$fullQualifiedClassName] = $tableName;
                } else {
                    self::$classTableMapping['tcaOverrides'][$fullQualifiedClassName] = $tableName;
                }
            }
        }
    }
}
