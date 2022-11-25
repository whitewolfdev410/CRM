<?php

namespace App\Core\Rbac;

use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * RBAC class
 */
class Rbac
{
    public $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function hasRole($permission)
    {
        if ($user = $this->app->auth->user()) {
            return $user->hasRole($permission);
        }

        return false;
    }

    public function can($permission)
    {
        if ($user = $this->app->auth->user()) {
            return $user->hasPermission($permission);
        }

        return false;
    }

    /**
     * Filters a route for the name Role
     * If the third parameter is null then return 403
     * Overwise the $result is returned
     *
     * @param  string $route Router pattern, i.e. "admin/*"
     * @param  array|string $roles The role(s) needed.
     * @param  mixed $result i.e. Redirect::to('/')
     * @param  bool $cumulative Must have all roles
     * @return mixed
     */
    public function routeNeedsRole(
        $route,
        $roles,
        $result = null,
        $cumulative = true
    ) {
        if (!is_array($roles)) {
            $roles = [$roles];
        }

        $filterName = implode('_', $roles) . '_' . substr(md5($route), 0, 6);

        if (!$result instanceof Closure) {
            $result = function () use ($roles, $result, $cumulative) {
                $hasARole = [];
                foreach ($roles as $role) {
                    $hasARole[] = $this->hasRole($role);
                }

                if (in_array(false, $hasARole) &&
                    ($cumulative || count(array_unique($hasARole)) == 1)
                ) {
                    if (!$result) {
                        Facade::getFacadeApplication()->abort(403);
                    }

                    return $result;
                }
            };
        }

        $this->app->router->filter($filterName, $result);
        $this->app->router->when($route, $filterName);
    }

    /**
     * Filters a route for the permission
     *
     * If the third parameter is null then return 403
     * Overwise the $result is returned
     *
     * @param  string $route
     * @param  array|string $permissions
     * @param  mixed $result
     * @param  bool $cumulative
     * @return mixed
     */
    public function routeNeedsPermission(
        $route,
        $permissions,
        $result = null,
        $cumulative = true
    ) {
        if (!is_array($permissions)) {
            $permissions = [$permissions];
        }

        $filterName =
            implode('_', $permissions) . '_' . substr(md5($route), 0, 6);

        if (!$result instanceof Closure) {
            $result = function () use ($permissions, $result, $cumulative) {
                $hasAPermission = [];
                foreach ($permissions as $permission) {
                    $hasAPermission[] = $this->can($permission);
                }

                if (in_array(false, $hasAPermission) &&
                    ($cumulative || count(array_unique($hasAPermission)) == 1)
                ) {
                    if (!$result) {
                        Facade::getFacadeApplication()->abort(403);
                    }

                    return $result;
                }
            };
        }

        $this->app->router->filter($filterName, $result);
        $this->app->router->when($route, $filterName);
    }

    /**
     * Filters a route for the permission
     *
     * If the third parameter is null then return 403
     * Overwise the $result is returned
     *
     * @param  string $route
     * @param  array|string $roles
     * @param  array|string $permissions
     * @param  mixed $result
     * @param  bool $cumulative
     * @return void
     */
    public function routeNeedsRoleOrPermission(
        $route,
        $roles,
        $permissions,
        $result = null,
        $cumulative = false
    ) {
        if (!is_array($roles)) {
            $roles = [$roles];
        }

        if (!is_array($permissions)) {
            $permissions = [$permissions];
        }

        $filterName =
            implode('_', $roles) . '_' . implode('_', $permissions) . '_' .
            substr(md5($route), 0, 6);

        if (!$result instanceof Closure) {
            $result =
                function () use ($roles, $permissions, $result, $cumulative) {
                    $hasARole = [];
                    foreach ($roles as $role) {
                        $hasARole[] = $this->hasRole($role);
                    }

                    $hasAPermission = [];
                    foreach ($permissions as $permission) {
                        $hasAPermission[] = $this->can($permission);
                    }

                    if (((in_array(false, $hasARole) ||
                            in_array(false, $hasAPermission))) &&
                        ($cumulative ||
                            count(array_unique(array_merge(
                                $hasARole,
                                $hasAPermission
                            ))) == 1)
                    ) {
                        if (!$result) {
                            Facade::getFacadeApplication()->abort(403);
                        }

                        return $result;
                    }
                };
        }

        $this->app->router->filter($filterName, $result);
        $this->app->router->when($route, $filterName);
    }
}
