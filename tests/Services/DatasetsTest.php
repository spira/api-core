<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\tests\Services;

use GuzzleHttp\Client;
use Mockery;
use Spira\Core\Contract\Exception\ServiceUnavailableException;
use Spira\Core\Model\Datasets\Countries;
use Spira\Core\tests\TestCase;

class DatasetsTest extends TestCase
{
    public function testCountries()
    {
        $client = new Client;
        $cache = \Mockery::mock('Illuminate\Contracts\Cache\Repository');

        $set = \Mockery::mock(Countries::class, [$client, $cache])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        /** @var \Illuminate\Support\Collection $countries */
        $countries = $set->getDataset();
        $country = $countries->first();

        $this->assertInstanceOf('Illuminate\Support\Collection', $countries);
        $this->assertArrayHasKey('country_name', $country);
        $this->assertArrayHasKey('country_code', $country);
        $this->assertGreaterThan(1, $countries->count());
    }

    public function testCountriesServiceUnavailable()
    {

        $this->setExpectedExceptionRegExp(
            ServiceUnavailableException::class,
            '/unavailable/i',
            0
        );

        $client = new Client;
        $cache = Mockery::mock('Illuminate\Contracts\Cache\Repository');

        $set = Mockery::mock(Countries::class, [$client, $cache])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $set->shouldReceive('getEndpoint')->once()->andReturn('https://restcountries.eu/foobar');

        $countries = $set->getDataset();
    }
}
