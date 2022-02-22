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

namespace PSB\PsbFoundation\Service\Configuration;

use Doctrine\Common\Annotations\AnnotationReader;
use InvalidArgumentException;
use JsonException;
use PSB\PsbFoundation\Annotation\TCA\AbstractTcaFalFieldAnnotation;
use PSB\PsbFoundation\Annotation\TCA\AbstractTcaFieldAnnotation;
use PSB\PsbFoundation\Annotation\TCA\Checkbox;
use PSB\PsbFoundation\Annotation\TCA\Ctrl;
use PSB\PsbFoundation\Annotation\TCA\Select;
use PSB\PsbFoundation\Annotation\TCA\TcaAnnotationInterface;
use PSB\PsbFoundation\Exceptions\MisconfiguredTcaException;
use PSB\PsbFoundation\Traits\PropertyInjection\ConnectionPoolTrait;
use PSB\PsbFoundation\Traits\PropertyInjection\ExtensionInformationServiceTrait;
use PSB\PsbFoundation\Traits\PropertyInjection\LocalizationServiceTrait;
use PSB\PsbFoundation\Utility\StringUtility;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException;
use TYPO3\CMS\Extbase\Persistence\ClassesConfiguration;
use TYPO3\CMS\Extbase\Persistence\ClassesConfigurationFactory;

/**
 * Class TcaService
 *
 * @package PSB\PsbFoundation\Service\Configuration
 */
class TcaService
{
    use ConnectionPoolTrait, ExtensionInformationServiceTrait, LocalizationServiceTrait;

    public const UNSET_KEYWORD = 'UNSET';

    protected const PROTECTED_COLUMNS = [
        'crdate',
        'pid',
        'tstamp',
        'uid',
    ];

    /**
     * @var bool
     */
    protected static bool $allowCaching = true;

    /**
     * @var array
     */
    protected static array $classTableMapping = [];

    /**
     * @var ClassesConfiguration
     */
    protected ClassesConfiguration $classesConfiguration;

    /**
     * @var ClassesConfigurationFactory
     */
    protected ClassesConfigurationFactory $classesConfigurationFactory;

    /**
     * @param ClassesConfigurationFactory $classesConfigurationFactory
     */
    public function __construct(ClassesConfigurationFactory $classesConfigurationFactory)
    {
        $this->classesConfigurationFactory = $classesConfigurationFactory;
        $this->classesConfiguration = $this->classesConfigurationFactory->createClassesConfiguration();
    }

    /**
     * This function will be executed when the core builds the TCA, but as it does not return an array there will be no
     * entry for the required file, instead this function expands the TCA on its own by scanning through the domain
     * models of all registered extensions (extensions which provide an ExtensionInformation class, see
     * \PSB\PsbFoundation\Data\ExtensionInformationInterface).
     * Transient domain models (those without a corresponding table in the database) will be skipped.
     *
     * @param bool $overrideMode If set to false, the configuration of all original domain models (not extending other
     *                           domain models) is added to the TCA.
     *                           If set to true, the configuration of all extending domain models is added to the TCA.
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws InvalidConfigurationTypeException
     * @throws JsonException
     * @throws MisconfiguredTcaException
     * @throws ReflectionException
     */
    public function buildTca(bool $overrideMode): void
    {
        if (false === self::$allowCaching || empty(self::$classTableMapping)) {
            $this->buildClassesTableMapping();
        }

        if ($overrideMode) {
            $key = 'tcaOverrides';
        } else {
            $key = 'tca';
        }

        if (isset(self::$classTableMapping[$key])) {
            foreach (self::$classTableMapping[$key] as $fullQualifiedClassName => $tableName) {
                $this->buildFromDocComment($fullQualifiedClassName, $overrideMode, $tableName);
            }
        }
    }

    /**
     * @param string $className
     *
     * @return string
     */
    public function convertClassNameToTableName(string $className): string
    {
        if ($this->classesConfiguration->hasClass($className)) {
            $classSettings = $this->classesConfiguration->getConfigurationFor($className);

            if (isset($classSettings['tableName']) && '' !== $classSettings['tableName']) {
                return $classSettings['tableName'];
            }
        }

        $classNameParts = explode('\\', $className);

        // Skip vendor and product name for core classes
        if (StringUtility::beginsWith($className, 'TYPO3\\CMS\\')) {
            $classPartsToSkip = 2;
        } else {
            $classPartsToSkip = 1;
        }

        return 'tx_' . strtolower(implode('_', array_slice($classNameParts, $classPartsToSkip)));
    }

    /**
     * @param string      $propertyName
     * @param string|null $className
     *
     * @return string
     */
    public function convertPropertyNameToColumnName(string $propertyName, string $className = null): string
    {
        if (null !== $className && $this->classesConfiguration->hasClass($className)) {
            $configuration = $this->classesConfiguration->getConfigurationFor($className);

            if (isset($configuration['properties'][$propertyName])) {
                return $configuration['properties'][$propertyName]['fieldName'];
            }
        }

        return GeneralUtility::camelCaseToLowerCaseUnderscored($propertyName);
    }

    protected function buildClassesTableMapping(): void
    {
        self::$classTableMapping = [];
        $allExtensionInformation = $this->extensionInformationService->getExtensionInformation();

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

                if (!class_exists($fullQualifiedClassName)) {
                    continue;
                }

                $reflectionClass = GeneralUtility::makeInstance(ReflectionClass::class, $fullQualifiedClassName);

                if ($reflectionClass->isAbstract() || $reflectionClass->isInterface()) {
                    continue;
                }

                $tableName = $this->convertClassNameToTableName($fullQualifiedClassName);

                if (StringUtility::beginsWith($tableName,
                    'tx_' . mb_strtolower($extensionInformation->getExtensionName()))) {
                    self::$classTableMapping['tca'][$fullQualifiedClassName] = $tableName;
                } else {
                    self::$classTableMapping['tcaOverrides'][$fullQualifiedClassName] = $tableName;
                }
            }
        }
    }

    /**
     * @param string $className
     * @param bool   $overrideMode
     * @param string $tableName
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws InvalidConfigurationTypeException
     * @throws JsonException
     * @throws MisconfiguredTcaException
     * @throws ReflectionException
     */
    protected function buildFromDocComment(string $className, bool $overrideMode, string $tableName): void
    {
        $annotationReader = new AnnotationReader();
        $reflection = GeneralUtility::makeInstance(ReflectionClass::class, $className);

        /** @var Ctrl|null $ctrl */
        $ctrl = $annotationReader->getClassAnnotation($reflection, Ctrl::class);

        if (!$overrideMode && null === $ctrl) {
            // @TODO: emit warning?
            return;
        }

        $extensionKey = $this->extensionInformationService->extractExtensionInformationFromClassName($className)['extensionKey'];
        $defaultLabelPath = 'LLL:EXT:' . $extensionKey . '/Resources/Private/Language/Backend/Configuration/TCA/';

        if (isset($GLOBALS['TCA'][$tableName])) {
            $defaultLabelPath .= 'Overrides/' . $tableName . '.xlf:';
        } else {
            $defaultLabelPath .= $tableName . '.xlf:';
        }

        $properties = $reflection->getProperties();
        $columnConfigurations = [];

        foreach ($properties as $property) {
            $docComment = $annotationReader->getPropertyAnnotations($property);

            foreach ($docComment as $annotation) {
                if ($annotation instanceof TcaAnnotationInterface) {
                    $columnName = $this->convertPropertyNameToColumnName($property->getName(), $className);

                    if ('' === $annotation->getLabel()) {
                        $label = $defaultLabelPath . $property->getName();
                        $this->localizationService->translationExists($label);
                        $annotation->setLabel($label);
                    }

                    if (($annotation instanceof Checkbox || $annotation instanceof Select)
                        && [] !== $annotation->getItems()
                        && ArrayUtility::isAssociative($annotation->getItems()
                        )) {
                        $annotation->setItems($this->processSelectItemsArray($annotation->getItems(),
                            $defaultLabelPath . $property->getName() . '.'));
                    }

                    $columnConfigurations[$columnName] = $annotation;
                }
            }
        }

        if ([] === $columnConfigurations) {
            // No annotated properties found in class. Do nothing.
            return;
        }

        if (!$overrideMode) {
            $GLOBALS['TCA'][$tableName] = $this->getDummyConfiguration($tableName);

            // default title may be overwritten by Ctrl-annotation in next block
            $title = $defaultLabelPath . 'ctrl.title';
            $this->localizationService->translationExists($title);
            $GLOBALS['TCA'][$tableName]['ctrl']['title'] = $title;
        }

        if (null !== $ctrl) {
            $ctrlProperties = $ctrl->toArray();

            if ($overrideMode) {
                $ctrlProperties = array_filter($ctrlProperties, static function ($key) use ($ctrl) {
                    return in_array($key, $ctrl->getSetProperties(), true);
                }, ARRAY_FILTER_USE_KEY);
            }

            foreach ($ctrlProperties as $property => $value) {
                if (self::UNSET_KEYWORD === $value) {
                    unset($GLOBALS['TCA'][$tableName]['ctrl'][$property]);
                } else {
                    $GLOBALS['TCA'][$tableName]['ctrl'][$property] = $value;
                }
            }
        }

        foreach ($columnConfigurations as $columnName => $annotation) {
            if ($annotation instanceof AbstractTcaFalFieldAnnotation) {
                $columnConfiguration = $annotation->toArray($columnName);
            } else {
                $columnConfiguration = $annotation->toArray();
            }

            ExtensionManagementUtility::addTCAcolumns($tableName, [$columnName => $columnConfiguration]);

            if (AbstractTcaFieldAnnotation::TYPE_LIST_NONE === $annotation->getTypeList()) {
                // Do not add this field to any type. It will not be visible in the backend.
                continue;
            }

            $position = $annotation->getPosition();

            if ('' !== $position) {
                [$prefix, $value] = GeneralUtility::trimExplode(':', $position);

                if ('tab' === $prefix) {
                    $tabLabel = $defaultLabelPath . 'tab.' . $value;

                    if (!$this->localizationService->translationExists($tabLabel, false)) {
                        $tabLabel = $value;
                    }

                    $columnName = '--div--;' . $tabLabel . ', ' . $columnName;
                    $position = '';
                }
            }

            ExtensionManagementUtility::addToAllTCAtypes($tableName, $columnName, $annotation->getTypeList() ?? '',
                $position);
        }

        $this->validateConfiguration($tableName);
    }

    /**
     * @param string $tableName
     *
     * @return array
     */
    protected function getDummyConfiguration(string $tableName): array
    {
        return [
            'types'    => [
                0 => ['showitem' => ''],
            ],
            'palettes' => [],
            'columns'  => [
                'endtime'          => [
                    'config'  => [
                        'behaviour'  => [
                            'allowLanguageSynchronization' => true,
                        ],
                        'default'    => 0,
                        'eval'       => 'datetime, int',
                        'range'      => [
                            'upper' => mktime(0, 0, 0, 1, 1, 2038),
                        ],
                        'renderType' => 'inputDateTime',
                        'type'       => 'input',
                    ],
                    'exclude' => true,
                    'label'   => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.endtime',
                ],
                'hidden'           => [
                    'config'  => [
                        'items'      => [
                            [
                                0                    => '',
                                1                    => '',
                                'invertStateDisplay' => true,
                            ],
                        ],
                        'renderType' => 'checkboxToggle',
                        'type'       => 'check',
                    ],
                    'exclude' => true,
                    'label'   => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.enabled',
                ],
                'l10n_diffsource'  => [
                    'config' => [
                        'default' => '',
                        'type'    => 'passthrough',
                    ],
                ],
                'l10n_parent'      => [
                    'config'      => [
                        'default'             => 0,
                        'foreign_table'       => $tableName,
                        'foreign_table_where' => 'AND ' . $tableName . '.pid=###CURRENT_PID### AND ' . $tableName . '.sys_language_uid IN (-1,0)',
                        'items'               => [
                            ['', 0],
                        ],
                        'renderType'          => 'selectSingle',
                        'type'                => 'select',
                    ],
                    'displayCond' => 'FIELD:sys_language_uid:>:0',
                    'label'       => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
                ],
                'starttime'        => [
                    'config'  => [
                        'behaviour'  => [
                            'allowLanguageSynchronization' => true,
                        ],
                        'default'    => 0,
                        'eval'       => 'datetime, int',
                        'renderType' => 'inputDateTime',
                        'type'       => 'input',
                    ],
                    'exclude' => true,
                    'label'   => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.starttime',
                ],
                'sys_language_uid' => [
                    'config'  => [
                        'type' => 'language',
                    ],
                    'exclude' => true,
                    'label'   => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
                ],
            ],
        ];
    }

    /**
     * @param array  $items
     * @param string $labelPath
     *
     * @return array
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws InvalidConfigurationTypeException
     * @throws JsonException
     */
    protected function processSelectItemsArray(array $items, string $labelPath): array
    {
        $selectItems = [];

        foreach ($items as $key => $value) {
            $identifier = GeneralUtility::underscoredToLowerCamelCase($key);
            $label = $labelPath . $identifier;

            if (!$this->localizationService->translationExists($label, false)) {
                $label = ucfirst($identifier);
            }

            $selectItems[] = [$label, $value];
        }

        return $selectItems;
    }

    /**
     * @param string $tableName
     *
     * @throws MisconfiguredTcaException
     */
    protected function validateConfiguration(string $tableName): void
    {
        $configuration = $GLOBALS['TCA'][$tableName];

        if (isset($configuration['ctrl']['sortby'])) {
            if (isset($configuration['ctrl']['default_sortby'])) {
                throw new MisconfiguredTcaException($tableName . ': You have to decide whether to use sortby or default_sortby. Your current configuration defines both of them.',
                    1541107594);
            }

            if (in_array($configuration['ctrl']['sortby'], self::PROTECTED_COLUMNS, true)) {
                throw new MisconfiguredTcaException($tableName . ': Your current configuration would overwrite a reserved system column with sorting values!',
                    1541107601);
            }
        }
    }
}
