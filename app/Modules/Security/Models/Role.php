<?php

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    public const ADMIN = 'admin';

    public const BOSS = 'boss';

    public const MANAGER = 'manager';

    public const EMPLOYEE = 'employee';

    /**
     * @return list<string>
     */
    public static function systemSlugs(): array
    {
        return [
            self::ADMIN,
            self::BOSS,
            self::MANAGER,
            self::EMPLOYEE,
        ];
    }

    /**
     * Roles that may review, approve, and mark tasks Done.
     *
     * @return list<string>
     */
    public static function privileged(): array
    {
        return [
            self::ADMIN,
            self::BOSS,
            self::MANAGER,
        ];
    }

    /**
     * Roles that bypass workspace scoping (cross-tenant executives).
     *
     * @return list<string>
     */
    public static function executives(): array
    {
        return [
            self::ADMIN,
            self::BOSS,
        ];
    }

    /**
     * Roles that may access the /admin panel.
     *
     * @return list<string>
     */
    public static function adminPanel(): array
    {
        return self::privileged();
    }

    /**
     * Map obsolete role slugs onto the four system roles.
     */
    public static function remapSlug(string $slug): string
    {
        return match (strtolower($slug)) {
            self::ADMIN, 'ceo', 'owner' => self::ADMIN,
            self::BOSS => self::BOSS,
            self::MANAGER => self::MANAGER,
            default => self::EMPLOYEE,
        };
    }

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }
}
