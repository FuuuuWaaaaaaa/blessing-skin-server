<?php

namespace App\Models;

use App\Models\Concerns\HasPassword;
use App\Models\Concerns\SearchString;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

/**
 * @property int         $uid
 * @property string      $email
 * @property string      $password
 * @property string      $nickname
 * @property string|null $locale
 * @property int         $avatar
 * @property int         $score
 * @property int         $permission
 * @property string      $ip
 * @property bool        $is_dark_mode
 * @property string      $last_sign_at
 * @property string      $register_at
 * @property bool        $verified
 * @property string      $player_name
 * @property Collection  $players
 * @property Collection  $closet
 */
class User extends Authenticatable
{
    use Notifiable;
    use HasFactory;
    use HasPassword;
    use HasApiTokens;
    use SearchString;

    public const BANNED = -1;
    public const NORMAL = 0;
    public const ADMIN = 1;
    public const SUPER_ADMIN = 2;

    protected $primaryKey = 'uid';
    public $timestamps = false;

    protected $fillable = [
        'email', 'nickname', 'avatar', 'score', 'permission', 'last_sign_at',
    ];

    protected $casts = [
        'uid' => 'integer',
        'score' => 'integer',
        'avatar' => 'integer',
        'permission' => 'integer',
        'verified' => 'bool',
        'is_dark_mode' => 'bool',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $searchStringColumns = [
        'uid',
        'email' => ['searchable' => true],
        'nickname' => ['searchable' => true],
        'avatar', 'score', 'permission', 'ip',
        'last_sign_at' => ['date' => true],
        'register_at' => ['date' => true],
        'verified' => ['boolean' => true],
        'is_dark_mode' => ['boolean' => true],
    ];

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            $user->fillLegacyRequiredAttributes();
        });
    }

    public function isAdmin(): bool
    {
        return $this->permission >= static::ADMIN;
    }

    public function closet()
    {
        return $this->belongsToMany(Texture::class, 'user_closet')->withPivot('item_name');
    }

    public function getPlayerNameAttribute()
    {
        $player = $this->players->first();

        return $player ? $player->name : '';
    }

    public function setPlayerNameAttribute($value)
    {
        $player = $this->players->first();
        if ($player) {
            $player->name = $value;
            $player->save();
        }
    }

    public function delete()
    {
        Player::where('uid', $this->uid)->delete();

        return parent::delete();
    }

    public function players()
    {
        return $this->hasMany(Player::class, 'uid');
    }

    public function getAuthIdentifier()
    {
        return $this->uid;
    }

    /**
     * Keep registrations compatible with older users tables that still have
     * required columns removed from the current application schema.
     */
    private function fillLegacyRequiredAttributes(): void
    {
        $connection = $this->getConnection();
        if ($connection->getDriverName() !== 'mysql') {
            return;
        }

        $table = $connection->getTablePrefix().$this->getTable();
        $quote = static fn (string $identifier): string => chr(96)
            .str_replace(chr(96), chr(96).chr(96), $identifier)
            .chr(96);
        $columns = $connection->select(
            'SHOW FULL COLUMNS FROM '.$quote($table)
        );
        $registerAt = $this->fromDateTime(
            $this->attributes['register_at'] ?? now()
        );
        $lastSignAt = $this->fromDateTime(
            $this->attributes['last_sign_at'] ?? $registerAt
        );
        $ip = $this->attributes['ip'] ?? '';

        foreach ($columns as $column) {
            $name = (string) $column->Field;
            $extra = strtolower(trim((string) ($column->Extra ?? '')));
            $normalized = strtolower((string) preg_replace('/[^a-z0-9]/', '', $name));
            $legacyColumn = $this->isLegacyColumn($normalized);

            if (
                (
                    array_key_exists($name, $this->attributes)
                    && $this->attributes[$name] !== null
                )
                || (!$legacyColumn && strtoupper((string) $column->Null) !== 'NO')
                || $column->Default !== null
                || str_contains($extra, 'auto_increment')
                || str_contains($extra, 'virtual')
                || str_contains($extra, 'stored')
            ) {
                continue;
            }

            $type = strtolower(trim((string) $column->Type));
            $value = match (true) {
                in_array($normalized, [
                    'regdate', 'registerdate', 'registrationdate',
                    'registeredat', 'registrationat', 'createdat',
                ], true) => $this->legacyDateValue($type, $registerAt),
                in_array($normalized, [
                    'lastlogin', 'lastlogindate', 'lastloginat',
                    'lastsignat', 'lastsignedat',
                ], true) => $this->legacyDateValue($type, $lastSignAt),
                in_array($normalized, [
                    'regip', 'registerip', 'registrationip', 'lastip',
                    'lastloginip',
                ], true) => $ip,
                in_array($normalized, [
                    'username', 'realname', 'name', 'displayname',
                ], true) => $this->attributes['nickname'] ?? '',
                str_contains($type, 'enum(') => $this->firstEnumValue($type),
                preg_match('/(?:tinyint|smallint|mediumint|int|bigint|decimal|float|double|bit)/', $type) === 1 => 0,
                str_contains($type, 'json') => '{}',
                preg_match('/(?:date|time|year)/', $type) === 1 => $registerAt,
                default => '',
            };

            $this->setAttribute($name, $value);
        }
    }

    private function firstEnumValue(string $type): string
    {
        $start = strpos($type, '(');
        $end = strrpos($type, ')');
        if ($start === false || $end === false || $end <= $start) {
            return '';
        }

        $values = str_getcsv(substr($type, $start + 1, $end - $start - 1), ',', "'");

        return (string) ($values[0] ?? '');
    }

    private function isLegacyColumn(string $normalized): bool
    {
        return in_array($normalized, [
            'regdate',
            'registerdate',
            'registrationdate',
            'registeredat',
            'registrationat',
            'createdat',
            'regip',
            'registerip',
            'registrationip',
            'lastip',
            'lastlogin',
            'lastlogindate',
            'lastloginat',
            'lastloginip',
            'lastsignat',
            'lastsignedat',
            'islogged',
            'hassession',
        ], true);
    }

    private function legacyDateValue(string $type, string $date): string|int
    {
        if (preg_match('/(?:tinyint|smallint|mediumint|int|bigint)/', $type) === 1) {
            return now()->timestamp;
        }

        return $date;
    }
}
