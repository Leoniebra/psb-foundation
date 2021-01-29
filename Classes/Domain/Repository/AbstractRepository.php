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

namespace PSB\PsbFoundation\Domain\Repository;

use PSB\PsbFoundation\Traits\InjectionTrait;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Class AbstractRepository
 *
 * @package PSB\PsbFoundation\Domain\Repository
 */
abstract class AbstractRepository extends Repository
{
    use InjectionTrait;

    /**
     * @return array|QueryResultInterface
     */
    public function findTcaSelectItems()
    {
        return $this->findAll();
    }
}
