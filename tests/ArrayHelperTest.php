<?php

namespace Spira\Core\tests;

use Spira\Core\Helpers\Arr;

class ArrayHelperTest extends TestCase
{
    public function testRecursiveMerge()
    {
        $firstArray = [

            'firstInner' => [
                'valueSubstitute' => 1,
                'valueAdded' => [
                    'one' => 'one',
                ],
                'noSuchValue' => [
                    'no',
                ],
            ],
            'secondInner' => [],

        ];

        $secondArray = [
            'firstInner' => [
                'valueSubstitute' => 2,
                'valueAdded' => [
                    'two' => 'two',
                ],
            ],
            'secondInner' => [
                'three',
            ],
        ];

        $expectedResult = [
            'firstInner' => [
                'valueSubstitute' => 2,
                'valueAdded' => [
                    'one' => 'one',
                    'two' => 'two',
                ],
                'noSuchValue' => [
                    'no',
                ],
            ],
            'secondInner' => [
                'three',
            ],
        ];

        $this->assertEquals($expectedResult, Arr::merge($firstArray, $secondArray));
    }
}
