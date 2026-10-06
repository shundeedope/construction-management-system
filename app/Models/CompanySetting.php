<?php

namespace App\Models;

use App\Enums\CompanySettingType;
use App\Models\Concerns\LogsDomainActivity;
use Database\Factories\CompanySettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property CompanySettingType $type
 * @property string|null $description
 */
class CompanySetting extends Model
{
    /** @use HasFactory<CompanySettingFactory> */
    use HasFactory, LogsDomainActivity;

    protected $fillable = ['key', 'value', 'type', 'description'];

    /**
     * The stored `value` is deliberately absent: an encrypted setting must never reach the
     * activity log, as plaintext or as ciphertext.
     *
     * @var array<int, string>
     */
    protected array $activityLogAttributes = ['key', 'type', 'description'];

    protected function casts(): array
    {
        return ['type' => CompanySettingType::class];
    }

    /**
     * The value cast to its declared type, decrypting encrypted settings.
     */
    public function typedValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            CompanySettingType::Integer => (int) $this->value,
            CompanySettingType::Boolean => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            CompanySettingType::Json => json_decode((string) $this->value, true, 512, JSON_THROW_ON_ERROR),
            CompanySettingType::Encrypted => Crypt::decryptString((string) $this->value),
            CompanySettingType::String => (string) $this->value,
        };
    }
}
