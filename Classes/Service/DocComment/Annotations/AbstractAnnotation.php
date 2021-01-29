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

namespace PSB\PsbFoundation\Service\DocComment\Annotations;

use Exception;
use PSB\PsbFoundation\Service\DocComment\DocCommentParserService;
use PSB\PsbFoundation\Utility\ObjectUtility;
use PSB\PsbFoundation\Utility\ValidationUtility;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class AbstractAnnotation
 *
 * @package PSB\PsbFoundation\Service\DocComment\Annotations
 */
abstract class AbstractAnnotation
{
    /**
     * AbstractAnnotation constructor.
     *
     * Maps associative arrays to object properties. Requires the class to have appropriate setter-methods.
     *
     * @param array $data
     *
     * @throws Exception
     */
    public function __construct(array $data = [])
    {
        $debugBacktrace = debug_backtrace();
        $backtraceClasses = [];

        foreach ($debugBacktrace as $step) {
            $backtraceClasses[] = $step['class'];
        }

        if (!in_array(DocCommentParserService::class, $backtraceClasses, true)) {
            // Don't let Doctrine's AnnotationReader continue, as it might throw exceptions because it is not able to
            // resolve elements of array constants.
            return;
        }

        if (!empty($data)) {
            $reflectionClass = GeneralUtility::makeInstance(ReflectionClass::class, $this);

            foreach ($data as $propertyName => $propertyValue) {
                $setterMethodName = 'set' . ucfirst($propertyName);

                if ($reflectionClass->hasMethod($setterMethodName)) {
                    $reflectionMethod = GeneralUtility::makeInstance(ReflectionMethod::class, $this, $setterMethodName);
                    $reflectionMethod->invoke($this, $propertyValue);
                } else {
                    throw new RuntimeException(static::class . ': Class doesn\'t have a method named "' . $setterMethodName . '"!',
                        1610459852);
                }
            }
        }
    }

    /**
     * @param string $targetScope
     *
     * @return array
     * @throws ReflectionException
     */
    public function toArray(string $targetScope): array
    {
        ValidationUtility::checkValueAgainstConstant(DocCommentParserService::ANNOTATION_TARGETS, $targetScope);

        return ObjectUtility::toArray($this);
    }
}
