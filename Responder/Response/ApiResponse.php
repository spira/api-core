<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\Responder\Response;

use InvalidArgumentException;
use Illuminate\Http\Response;
use Spira\Core\Responder\Contract\TransformerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiResponse extends Response
{
    /** @var TransformerInterface */
    protected $transformer = null;

    private $localizeToRegion = false;

    /**
     * Set the transformer to use for building entities.
     * @param TransformerInterface $transformer
     * @return ApiResponse
     */
    public function transformer(TransformerInterface $transformer)
    {
        $this->transformer = $transformer;

        return $this;
    }

    /**
     * Respond with a created response and associate a location if provided.
     * @param null $location
     * @return ApiResponse
     */
    public function created($location = null)
    {
        if (! is_null($location)) {
            $this->header('Location', $location);
        }

        return $this
            ->setContent(null)
            ->setStatusCode(self::HTTP_CREATED);
    }

    /**
     * Respond with a no content response.
     *
     * @param  int  $code
     * @return ApiResponse
     */
    public function noContent($code = self::HTTP_NO_CONTENT)
    {
        return $this
            ->setStatusCode($code)
            ->setContent(null);
    }

    /**
     * Bind an item to a transformer and start building a response.
     * @param $item
     * @param int $statusCode
     * @param bool $replaceHeaders
     * @return ApiResponse
     */
    public function item($item, $statusCode = null, $replaceHeaders = true)
    {
        if ($this->transformer) {
            $item = $this->transformer->transformItem($item, $this->getTransformerOptions());
        }

        if (is_null($statusCode)) {
            $statusCode = $this->getStatusCode();
        }

        if (is_null($statusCode)) {
            $statusCode = self::HTTP_OK;
        }

        return $this
            ->header('Content-Type', 'application/json', $replaceHeaders)
            ->setContent($this->encode($item))
            ->setStatusCode($statusCode);
    }

    /**
     * @param $items
     * @param int $statusCode
     * @return ApiResponse
     */
    public function collection($items, $statusCode = null)
    {
        if ($this->transformer) {
            $items = $this->transformer->transformCollection($items, $this->getTransformerOptions());
        }

        if (is_null($statusCode)) {
            $statusCode = $this->getStatusCode();
        }

        if (is_null($statusCode)) {
            $statusCode = self::HTTP_OK;
        }

        return $this
            ->header('Content-Type', 'application/json')
            ->setContent($this->encode($items))
            ->setStatusCode($statusCode);
    }

    /**
     * Build paginated response.
     * @param $items
     * @param null $offset
     * @param null $totalCount
     * @return ApiResponse
     */
    public function paginatedCollection($items, $offset = null, $totalCount = null)
    {
        $itemCount = count($items);
        $this->validateRange($itemCount);

        $rangeHeader = $this->prepareRangeHeader($itemCount, $offset, $totalCount);

        return $this
            ->header('Accept-Ranges', 'entities')
            ->header('Content-Type', 'application/json')
            ->header('Content-Range', $rangeHeader)
            ->collection($items, self::HTTP_PARTIAL_CONTENT);
    }

    /**
     * @param $itemCount
     * @return bool
     */
    protected function validateRange($itemCount)
    {
        if ($itemCount <= 0) {
            throw new HttpException(self::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE, 'Requested Range Not Satisfiable');
        }

        return true;
    }

    /**
     * @param $itemCount
     * @param $offset
     * @param $totalCount
     * @return array
     */
    protected function prepareRangeHeader($itemCount, $offset, $totalCount)
    {
        $offset = is_null($offset) ? 0 : $offset;
        $totalCount = is_null($totalCount) ? '*' : $totalCount;
        $rangeHeader = 'entities '.$offset.'-'.($itemCount + $offset - 1).'/'.$totalCount;

        return $rangeHeader;
    }

    /**
     * Json encode.
     * @param $data
     * @return string
     */
    protected function encode($data)
    {
        $debug = env('APP_DEBUG', false);
        $prettyPrint = $debug ? JSON_PRETTY_PRINT : 0;

        return json_encode($data, $prettyPrint);
    }

    /**
     * Creates a redirect response.
     *
     * @param  string  $url
     * @param  int     $status
     *
     * @throws InvalidArgumentException
     *
     * @return void
     */
    public function redirect($url, $status = 302)
    {
        if (empty($url)) {
            throw new InvalidArgumentException('Cannot redirect to an empty URL.');
        }

        $this->setStatusCode($status);
        $this->header('Location', $url);

        if (! $this->isRedirect()) {
            throw new InvalidArgumentException(sprintf('The HTTP status code is not a redirect ("%s" given).', $status));
        }
    }

    /**
     * Set the localization region (typically done right after instantiation of this class).
     *
     * @param $region
     */
    public function setLocalizationRegion($region)
    {
        $this->localizeToRegion = $region;
    }

    /**
     * Get options for the transformer based on current request state.
     * @return array
     */
    private function getTransformerOptions()
    {
        $transformerOptions = [];

        $transformerOptions['created'] = $this->getStatusCode() === self::HTTP_CREATED;

        if ($this->localizeToRegion) {
            $transformerOptions['region'] = $this->localizeToRegion;

            return $transformerOptions;
        }

        return $transformerOptions;
    }
}
