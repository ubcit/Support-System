<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CertificationExpectation extends Model {
    protected $fillable = ['certification_test_id', 'expected_data'];
    protected $casts = ['expected_data' => 'array'];
    public function test() { return $this->belongsTo(CertificationTest::class, 'certification_test_id'); }
}
