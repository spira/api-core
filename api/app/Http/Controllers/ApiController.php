<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spira\Model\Model\BaseModel;
use Laravel\Lumen\Routing\Controller;
use Spira\Model\Collection\Collection;
use App\Exceptions\BadRequestException;
use Spira\Rbac\Access\AuthorizesRequestsTrait;
use Spira\Responder\Response\ApiResponse;
use Spira\Responder\Contract\TransformerInterface;

abstract class ApiController extends Controller
{
    use AuthorizesRequestsTrait;

    protected $paginatorDefaultLimit = 10;
    protected $paginatorMaxLimit = 50;

    /**
     * @var TransformerInterface
     */
    protected $transformer;

    /**
     * Enable permissions checks.
     */
    protected $permissionsEnabled = false;

    /**
     * Name of the default role to check against
     * Designed for default rules
     * Should be set to false to enable route based permissions.
     */
    protected $defaultRole = 'admin';

    public function __construct(TransformerInterface $transformer)
    {
        $this->transformer = $transformer;
        $this->middleware('transaction');
    }

    /**
     * @return ApiResponse
     */
    public function getResponse()
    {
        return new ApiResponse();
    }

    /**
     * @return TransformerInterface
     */
    public function getTransformer()
    {
        return $this->transformer;
    }

    /**
     * @param Collection|BaseModel $modelOrCollection
     * @param Request $request
     * @return mixed
     */
    protected function getWithNested($modelOrCollection, Request $request)
    {
        $nested = $request->headers->get('With-Nested');
        if (! $nested) {
            return $modelOrCollection;
        }

        $requestedRelations = explode(', ', $nested);

        try {
            $modelOrCollection->load($requestedRelations);
        } catch (\BadMethodCallException $e) {
            throw new BadRequestException(sprintf('Invalid `With-Nested` request - one or more of the following relationships do not exist for %s:[%s]', get_class($modelOrCollection), $nested), null, $e);
        }

        return $modelOrCollection;
    }

    /**
     * Determine if the request is localised.
     *
     * @param  Request $request
     *
     * @return array
     */
    protected function isLocalised(Request $request)
    {
        if ($locale = $request->header('Content-Region')) {
            return ['locale' => $locale];
        }

        return [];
    }

    /**
     * Authorize a given action against a set of arguments.
     *
     * @param  mixed  $permission
     * @param  mixed|array  $arguments
     * @return void
     */
    public function checkPermission($permission, $arguments = [])
    {
        if (! $this->permissionsEnabled) {
            return;
        }

        if ($this->defaultRole) {
            $permission = $this->defaultRole;
        }

        $this->authorize($permission, $arguments);
    }
}
