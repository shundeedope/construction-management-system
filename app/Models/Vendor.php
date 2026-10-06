<?php

namespace App\Models;

use App\Enums\VendorType;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $company_name
 * @property VendorType|null $vendor_type
 * @property bool $is_active
 */
class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    protected $fillable = ['company_name', 'vendor_type', 'primary_contact_name', 'email', 'phone', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country', 'tax_identifier', 'insurance_expires_at', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['vendor_type' => VendorType::class, 'insurance_expires_at' => 'date', 'is_active' => 'boolean'];
    }

    /** @return HasMany<Contract, $this> */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<ComplianceRecord, $this> */
    public function complianceRecords(): HasMany
    {
        return $this->hasMany(ComplianceRecord::class);
    }
}
