<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CertificationTest extends Model {
    protected $fillable = ['name', 'description', 'category', 'tags', 'is_golden', 'is_active', 'created_by'];
    protected $casts = ['tags' => 'array', 'is_golden' => 'boolean', 'is_active' => 'boolean'];
    public function input() { return $this->hasOne(CertificationInput::class); }
    public function expectation() { return $this->hasOne(CertificationExpectation::class); }
    public function runs() { return $this->hasMany(CertificationRun::class); }
}
