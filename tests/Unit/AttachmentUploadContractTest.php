<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AttachmentUploadContractTest extends TestCase
{
    public function test_ac_8_component_exposes_progress_camera_capture_and_accessible_errors(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/js/Components/AttachmentUploader.vue',
        );

        $this->assertStringContainsString("request.upload.addEventListener('progress'", $source);
        $this->assertStringContainsString('capture="environment"', $source);
        $this->assertStringContainsString(':accept="accept"', $source);
        $this->assertStringContainsString('<progress', $source);
        $this->assertStringContainsString('aria-live="polite"', $source);
        $this->assertStringContainsString('fileInput.value?.focus()', $source);
        $this->assertStringContainsString('min-w-0', $source);
        $this->assertStringNotContainsString('http://', $source);
        $this->assertStringNotContainsString('https://', $source);
    }

    public function test_private_storage_and_apache_double_barrier_are_declared(): void
    {
        $filesystems = (string) file_get_contents(dirname(__DIR__, 2).'/config/filesystems.php');
        $runbook = (string) file_get_contents(dirname(__DIR__, 2).'/docs/ops/private-attachments.md');

        $this->assertStringContainsString("'private' => [", $filesystems);
        $this->assertStringContainsString("storage_path('app/private')", $filesystems);
        $this->assertStringContainsString('Require all denied', $runbook);
        $this->assertStringContainsString('XSendFilePath', $runbook);
        $this->assertStringContainsString('imagick', $runbook);
        $this->assertStringContainsString('libheif', $runbook);
    }
}
