<?php

declare(strict_types=1);

namespace LiteAdmin\Security;

use LiteAdmin\Attribute\Authorize;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;

/**
 * Evaluates Role-Based Access Control (RBAC) and permissions for Admin resources and actions.
 */
class PermissionGate
{
    /** @var callable|null fn(ServerRequestInterface $request): array{roles?: string[], permissions?: string[]} */
    private $userResolver = null;

    /**
     * Set a custom callback to resolve user roles and permissions from the HTTP request.
     *
     * @param callable $resolver fn(ServerRequestInterface $request): array{roles?: string[], permissions?: string[]}
     */
    public function setUserResolver(callable $resolver): self
    {
        $this->userResolver = $resolver;
        return $this;
    }

    /**
     * Check if the current request is authorized to perform an action on an entity resource.
     *
     * @param ServerRequestInterface $request
     * @param class-string $entityClass
     * @param string $action 'view', 'create', 'update', 'delete', 'export'
     */
    public function can(ServerRequestInterface $request, string $entityClass, string $action): bool
    {
        if (!class_exists($entityClass)) {
            return true;
        }

        $ref = new ReflectionClass($entityClass);
        $attributes = $ref->getAttributes(Authorize::class);

        // If no authorization rules are declared, allow by default
        if (empty($attributes)) {
            return true;
        }

        // If rules are declared but no user resolver is provided, deny by default
        if ($this->userResolver === null) {
            return false;
        }

        $userContext = ($this->userResolver)($request);
        $userRoles = array_map('strtolower', (array)($userContext['roles'] ?? []));
        $userPermissions = array_map('strtolower', (array)($userContext['permissions'] ?? []));

        // Superadmin bypass
        if (in_array('admin', $userRoles, true) || in_array('superadmin', $userRoles, true)) {
            return true;
        }

        $applicableRules = 0;

        foreach ($attributes as $attr) {
            /** @var Authorize $auth */
            $auth = $attr->newInstance();

            // Check if rule applies to this specific action (null means applies to all actions)
            if ($auth->action !== null && strtolower($auth->action) !== strtolower($action)) {
                continue;
            }

            $applicableRules++;

            // 1. Check Roles
            if (!empty($auth->roles)) {
                $requiredRoles = array_map('strtolower', $auth->roles);
                if (!empty(array_intersect($requiredRoles, $userRoles))) {
                    return true;
                }
            }

            // 2. Check Permissions
            if (!empty($auth->permissions)) {
                $requiredPerms = array_map('strtolower', $auth->permissions);
                if (!empty(array_intersect($requiredPerms, $userPermissions))) {
                    return true;
                }
            }
        }

        // If rules applied to this action and none matched, deny access
        return $applicableRules === 0;
    }
}
