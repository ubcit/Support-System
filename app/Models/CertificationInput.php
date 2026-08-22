<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CertificationInput extends Model {
    protected $fillable = ['certification_test_id', 'customer_phone', 'project_name', 'boss_notes', 'messages', 'attachments', 'provider', 'metadata'];
    protected $casts = ['messages' => 'array', 'attachments' => 'array', 'metadata' => 'array'];
    public function test() { return $this->belongsTo(CertificationTest::class, 'certification_test_id'); }
}
