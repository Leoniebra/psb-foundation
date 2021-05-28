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

namespace PSB\PsbFoundation\Domain\Repository\Typo3;

use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Class PageRepository
 *
 * @package PSB\PsbFoundation\Domain\Repository\Typo3
 */
class PageRepository extends Repository
{
    /**
     * @param string $module
     *
     * @return QueryResultInterface
     */
    public function findByModule(string $module): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching($query->equals('module', $module));

        return $query->execute();
    }
}
