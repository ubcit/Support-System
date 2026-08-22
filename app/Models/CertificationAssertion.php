<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CertificationAssertion extends Model {
    protected $fillable = ['certification_run_id', 'key', 'expected_value', 'actual_value', 'passed'];
    protected $casts = ['passed' => 'boolean'];
    public function run() { return $this->belongsTo(CertificationRun::class, 'certification_run_id'); }
}
