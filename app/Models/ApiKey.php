<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    public const ABILITY_GRADES_READ = 'grades:read';

    protected $fillable = [
        'name',
        'key_hash',
        'prefix',
        'abilities',
        'expires_at',
        'revoked_at',
        'created_by',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = ['key_hash'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function hasAbilities(array $required): bool
    {
        $granted = $this->abilities ?? [];

        foreach ($required as $ability) {
            if (! in_array($ability, $granted, true)) {
                return false;
            }
        }

        return true;
    }

    public function touchUsage(): void
    {
        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }

    /**
     * @return array{0: static, 1: string} [record, plaintext key shown once]
     */
    public static function generate(string $name, ?int $createdBy = null, array $abilities = [self::ABILITY_GRADES_READ]): array
    {
        $plain = 'cbt_live_'.Str::random(40);

        $record = static::create([
            'name' => $name,
            'key_hash' => hash('sha256', $plain),
            'prefix' => substr($plain, 0, 16),
            'abilities' => array_values($abilities),
            'created_by' => $createdBy,
        ]);

        return [$record, $plain];
    }
}
