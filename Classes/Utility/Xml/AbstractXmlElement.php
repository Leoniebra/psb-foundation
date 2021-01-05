<?php
declare(strict_types=1);
namespace PSB\PsbFoundation\Utility\Xml;

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

use PSB\PsbFoundation\Traits\AutoFillPropertiesTrait;
use PSB\PsbFoundation\Utility\ObjectUtility;
use ReflectionClass;
use ReflectionException;

/**
 * Class AbstractXmlElement
 *
 * @package PSB\PsbFoundation\Utility\Xml
 */
class AbstractXmlElement implements XmlElementInterface
{
    use AutoFillPropertiesTrait;

    /**
     * @var array
     */
    protected array $_attributes = [];

    /**
     * @var mixed
     */
    protected $_nodeValue = null;

    /**
     * @var int|null
     */
    protected ?int $_position = null;

    /**
     * @param array $childData
     *
     * @throws ReflectionException
     */
    public function __construct(array $childData)
    {
        foreach ($childData as $childKey => $childValues) {
            if (is_array($childValues)) {
                if (isset($childValues[XmlUtility::SPECIAL_KEYS['POSITION']])) {
                    $this->_setPosition($childValues[XmlUtility::SPECIAL_KEYS['POSITION']]);
                    unset ($childValues[XmlUtility::SPECIAL_KEYS['POSITION']]);
                }

                $onlyNodeValue = true;

                foreach ($childValues as $childValueKey => $childValue) {
                    if ($childValueKey !== XmlUtility::SPECIAL_KEYS['NODE_VALUE']) {
                        $onlyNodeValue = false;
                    }
                }

                if ($onlyNodeValue) {
                    $childData[$childKey] = $childValues[XmlUtility::SPECIAL_KEYS['NODE_VALUE']];
                }
            }
        }

        if (isset($childData[XmlUtility::SPECIAL_KEYS['ATTRIBUTES']])) {
            $this->_setAttributes($childData[XmlUtility::SPECIAL_KEYS['ATTRIBUTES']]);
        }

        if (isset($childData[XmlUtility::SPECIAL_KEYS['NODE_VALUE']])) {
            $this->_setNodeValue($childData[XmlUtility::SPECIAL_KEYS['NODE_VALUE']]);
        }

        $this->fillProperties($childData);
    }

    public static function getTagName(): string
    {
        return XmlUtility::sanitizeTagName((new ReflectionClass(static::class))->getShortName());
    }

    /**
     * @return array
     */
    public function _getAttributes(): array
    {
        return $this->_attributes;
    }

    /**
     * @param array $attributes
     */
    public function _setAttributes(array $attributes): void
    {
        $this->_attributes = $attributes;
    }

    /**
     * @return mixed
     */
    public function _getNodeValue()
    {
        return $this->_nodeValue;
    }

    /**
     * @param mixed $nodeValue
     */
    public function _setNodeValue($nodeValue): void
    {
        $this->_nodeValue = $nodeValue;
    }

    /**
     * @return int|null
     */
    public function _getPosition(): ?int
    {
        return $this->_position;
    }

    /**
     * @param int $position
     */
    public function _setPosition(int $position): void
    {
        $this->_position = $position;
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    public function toArray(): array
    {
        $propertiesArray = ObjectUtility::toArray($this);
        $array = [];

        foreach ($propertiesArray as $key => $value) {
            $array[XmlUtility::sanitizeTagName($key)] = $value;
        }

        if (!empty($this->_getAttributes())) {
            $array[XmlUtility::SPECIAL_KEYS['ATTRIBUTES']] = $this->_getAttributes();
        }

        if (null !== $this->_getNodeValue()) {
            $array[XmlUtility::SPECIAL_KEYS['NODE_VALUE']] = $this->_getNodeValue();
        }

        if (null !== $this->_getPosition()) {
            $array[XmlUtility::SPECIAL_KEYS['POSITION']] = $this->_getPosition();
        }

        return $array;
    }
}
