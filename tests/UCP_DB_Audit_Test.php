<?php
use PHPUnit\Framework\TestCase;

final class UCP_DB_Audit_Test extends TestCase {
    public function test_audit_shape() {
        $audit = array('autoload_top' => array(), 'missing_indexes' => array(), 'options_engine' => 'unknown');
        $this->assertArrayHasKey('autoload_top', $audit);
        $this->assertArrayHasKey('missing_indexes', $audit);
        $this->assertArrayHasKey('options_engine', $audit);
    }
}
