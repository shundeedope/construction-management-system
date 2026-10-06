<?php

namespace App\Services;

use App\Enums\CompanySettingType;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Crypt;

/**
 * Typed access to the `company_settings` key/value store. Encrypted settings are decrypted
 * on read and never exposed through the model's raw `value` column.
 */
class CompanySettings
{
    /** @var array<string, mixed> */
    private array $resolved = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        $setting = CompanySetting::query()->where('key', $key)->first();

        return $this->resolved[$key] = $setting?->typedValue() ?? $default;
    }

    public function set(string $key, mixed $value, CompanySettingType $type = CompanySettingType::String): CompanySetting
    {
        unset($this->resolved[$key]);

        return CompanySetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $this->encode($value, $type), 'type' => $type],
        );
    }

    public function flush(): void
    {
        $this->resolved = [];
    }

    public function companyName(): string
    {
        return (string) $this->get('company_name', 'Company');
    }

    public function currency(): string
    {
        return (string) $this->get('currency', 'USD');
    }

    public function complianceWarningDays(): int
    {
        return (int) $this->get('compliance_warning_days', 30);
    }

    public function maximumDailyHours(): float
    {
        return (float) $this->get('maximum_daily_hours', 16);
    }

    /**
     * The raw string persisted in the `value` column for the given type.
     */
    public function encode(mixed $value, CompanySettingType $type): ?string
    {
        return match ($type) {
            CompanySettingType::Boolean => $value ? '1' : '0',
            CompanySettingType::Json => json_encode($value, JSON_THROW_ON_ERROR),
            CompanySettingType::Encrypted => $value === null ? null : Crypt::encryptString((string) $value),
            default => $value === null ? null : (string) $value,
        };
    }
}
