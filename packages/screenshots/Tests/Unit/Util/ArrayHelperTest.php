<?php

declare(strict_types=1);
namespace TYPO3\CMS\Screenshots\Tests\Unit\Util;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Screenshots\Util\ArrayHelper;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ArrayHelperTest extends UnitTestCase
{
    /**
     * @test
     * @dataProvider getArrayByPathDataProvider
     *
     * @param array $array
     * @param string $path
     * @param array $expected
     */
    public function getArrayByPath(array $array, string $path, array $expected): void
    {
        self::assertEquals(ArrayHelper::getArrayByPath($array, $path), $expected);
    }

    public function getArrayByPathDataProvider(): array
    {
        $tca = [
            'ctrl' => [],
            'columns' => [
                'title' => [
                    'label' => 'title',
                    'config' => [],
                ],
            ]
        ];

        return [
            [
                'array' => $tca,
                'path' => 'ctrl',
                'expected' => [
                    'ctrl' => []
                ]
            ],
            [
                'array' => $tca,
                'path' => 'columns/title/label',
                'expected' => [
                    'columns' => [
                        'title' => [
                            'label' => 'title'
                        ]
                    ]
                ]
            ]
        ];
    }
}