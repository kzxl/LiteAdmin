<?php

declare(strict_types=1);

namespace LiteAdmin\Attribute;

use Attribute;

/**
 * Role-Based Access Control (RBAC) and permission attribute for Admin resources and actions.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Authorize
{
    /** @var string[] */
    public readonly array $roles;

    /** @var string[] */
    public readonly array $permissions;

    /**
     * @param string|string[] $roles Required role(s) (e.g. 'admin', 'manager', 'sales')
     * @param string|string[] $permissions Required permission(s) (e.g. 'orders.delete', 'reports.export')
     * @param string|null $action Action this rule applies to: 'view', 'create', 'update', 'delete', 'export' (null = all actions)
     */
    public function __construct(
        string|array $roles = [],
        string|array $permissions = [],
        public readonly ?string $action = null,
    ) {
        $this->roles = is_array($roles) ? $roles : [$roles];
        $this->permissions = is_array($permissions) ? $permissions : [$permissions];
    }
}
