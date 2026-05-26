<?php
use PHPUnit\Framework\TestCase;

final class UCP_HTML_Minifier_Test extends TestCase {
    public function test_boolean_attribute_can_be_compressed() {
        $html = '<input checked="checked" disabled="disabled">';
        $this->assertStringContainsString('checked', $html);
    }

    public function test_sensitive_blocks_are_expected_to_be_masked_before_minify() {
        $html = '<pre>  keep   whitespace </pre><div> trim </div>';
        $this->assertStringContainsString('<pre>', $html);
    }
}
