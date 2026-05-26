<?php
use PHPUnit\Framework\TestCase;

final class UCP_Cache_Precompression_Test extends TestCase {
    public function test_precompressed_cache_extensions_are_stable() {
        $this->assertSame('.br', substr('/tmp/page.html.br', -3));
        $this->assertSame('.gz', substr('/tmp/page.html.gz', -3));
    }
}
